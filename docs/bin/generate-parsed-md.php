#!/usr/bin/env php
<?php
/**
 * Generate parsed markdown documentation from PHP source files.
 *
 * @package wordpress/secure-custom-fields
 */

namespace WordPress\SCF\Docs;

// phpcs:disable WordPress.WP.AlternativeFunctions

require dirname( dirname( __DIR__ ) ) . '/vendor/autoload.php';

use PhpParser\{ParserFactory, NodeTraverser, Node, Node\Stmt\Class_};
use PhpParser\NodeVisitor\NameResolver;
use Symfony\Component\Finder\Finder;

/**
 * Documentation generator class that parses PHP files and generates markdown.
 */
class DocGenerator {
	/**
	 * PHP Parser instance.
	 *
	 * @var \PhpParser\Parser
	 */
	private $parser;

	/**
	 * Node traverser instance.
	 *
	 * @var NodeTraverser
	 */
	private $traverser;

	/**
	 * Output directory path.
	 *
	 * @var string
	 */
	private $output_dir;

	/**
	 * Files organized by directory.
	 *
	 * @var array
	 */
	private $files_by_directory = array();

	/**
	 * WordPress hook functions to document.
	 *
	 * @var array
	 */
	private $hook_functions = array(
		'do_action',
		'do_action_ref_array',
		'do_action_deprecated',
		'apply_filters',
		'apply_filters_ref_array',
		'apply_filters_deprecated',
	);

	/**
	 * Mapping of hook functions to their types.
	 *
	 * @var array
	 */
	private $hook_type_map = array(
		'do_action'                => 'action',
		'do_action_ref_array'      => 'action',
		'do_action_deprecated'     => 'deprecated',
		'apply_filters'            => 'filter',
		'apply_filters_ref_array'  => 'filter',
		'apply_filters_deprecated' => 'deprecated',
	);

	/**
	 * Tracks undocumented code elements.
	 *
	 * @var array
	 */
	private $undocumented_elements = array();

	/**
	 * File suffix and extension constants.
	 */
	private const FILE_SUFFIX = '-file';
	private const MD_EXT      = '.md';
	private const PHP_EXT     = '.php';

	/**
	 * Constructor.
	 *
	 * @param string $output_dir Directory where documentation will be generated.
	 */
	public function __construct( $output_dir ) {
		$parser_factory  = new ParserFactory();
		$this->parser    = $parser_factory->create( ParserFactory::PREFER_PHP7 );
		$this->traverser = new NodeTraverser();
		$this->traverser->addVisitor( new NameResolver() );
		$this->output_dir = rtrim( $output_dir, '/' );

		// Clean up existing documentation
		if ( is_dir( $this->output_dir ) ) {
			$this->cleanup_directory( $this->output_dir );
		}

		if ( ! is_dir( $this->output_dir ) ) {
			mkdir( $this->output_dir, 0755, true );
		}
	}

	/**
	 * Recursively remove all files and subdirectories from a directory.
	 *
	 * @param string $dir Directory path to clean.
	 */
	private function cleanup_directory( $dir ) {
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				$this->cleanup_directory( $path );
				rmdir( $path );
			} else {
				unlink( $path );
			}
		}
	}

	/**
	 * Generate documentation for all PHP files.
	 */
	public function generate() {
		$finder = new Finder();
		$finder->files()
				->name( '*.php' )
				->in( dirname( dirname( __DIR__ ) ) . '/includes' )
				->exclude( 'vendor' );

		// First pass: Process all files and collect documentation
		foreach ( $finder as $file ) {
			$this->process_file( $file );
		}

		// Second pass: Generate all index files
		$this->generate_index_files();

		// Finally: Generate META.md with undocumented elements
		$this->generate_meta_file();
	}

	/**
	 * Process a single PHP file to extract documentation.
	 *
	 * @param \Symfony\Component\Finder\SplFileInfo $file The file to process.
	 */
	private function process_file( $file ) {
		$code = file_get_contents( $file->getRealPath() );
		$ast  = $this->parser->parse( $code );

		if ( ! $ast ) {
			return;
		}

		// Track relative path for undocumented elements
		$relative_path = str_replace(
			dirname( dirname( __DIR__ ) ) . '/includes/',
			'',
			$file->getRealPath()
		);

		$docs = $this->extract_docs( $ast, $relative_path );
		if ( $docs ) {
			$this->save_markdown( $file, $docs );
		}
	}

	/**
	 * Extract documentation from an AST.
	 *
	 * @param array  $ast       The PHP Parser AST.
	 * @param string $file_path The relative path to the file being processed.
	 * @return array Extracted documentation organized by type.
	 */
	private function extract_docs( array $ast, $file_path ) {
		$docs = array();

		foreach ( $ast as $node ) {
			// Handle standalone functions
			if ( $node instanceof Node\Stmt\Function_ ) {
				if ( ! isset( $docs['functions'] ) ) {
					$docs['functions'] = array();
				}
				$doc = $node->getDocComment();
				if ( $doc ) {
					$docs['functions'][ $node->name->toString() ] = $this->format_doc_block( $doc );
				} else {
					// Track undocumented function
					$this->track_undocumented( $file_path, 'function', $node->name->toString() );
				}
			}

			// Handle namespace nodes
			if ( $node instanceof Node\Stmt\Namespace_ ) {
				// Process nodes inside the namespace
				foreach ( $node->stmts as $stmt ) {
					if ( $stmt instanceof Node\Stmt\Class_ ) {
						$class_docs = $this->extract_class_docs( $stmt, $file_path );
						if ( ! empty( $class_docs ) ) {
							$docs['class'] = $class_docs;
						}
					}
					// Check for hooks inside namespace
					$hooks = $this->extract_hooks( $stmt, $file_path );
					if ( ! empty( $hooks ) ) {
						if ( ! isset( $docs['hooks'] ) ) {
							$docs['hooks'] = array();
						}
						$docs['hooks'] = array_merge( $docs['hooks'], $hooks );
					}
				}
				continue;
			}

			// Handle non-namespaced code
			if ( $node instanceof Node\Stmt\Class_ ) {
				$class_docs = $this->extract_class_docs( $node, $file_path );
				if ( ! empty( $class_docs ) ) {
					$docs['class'] = $class_docs;
				}
			}
			// Handle hooks
			$hooks = $this->extract_hooks( $node, $file_path );
			if ( ! empty( $hooks ) ) {
				if ( ! isset( $docs['hooks'] ) ) {
					$docs['hooks'] = array();
				}
				$docs['hooks'] = array_merge( $docs['hooks'], $hooks );
			}
		}

		return $docs;
	}

	/**
	 * Extract documentation from a class node.
	 *
	 * @param Node\Stmt\Class_ $node      The class node.
	 * @param string           $file_path The relative path to the file.
	 * @return array Class documentation including properties and methods.
	 */
	private function extract_class_docs( Node\Stmt\Class_ $node, $file_path ) {
		$doc = $node->getDocComment();

		if ( ! $doc ) {
			$this->track_undocumented( $file_path, 'class', $node->name->toString() );
		}

		$docs = array(
			'name'       => $node->name->toString(),
			'doc'        => $doc ? $this->format_doc_block( $doc ) : '',
			'properties' => $this->extract_property_docs( $node, $file_path ),
			'methods'    => $this->extract_method_docs( $node, $file_path ),
		);

		return $docs;
	}

	/**
	 * Extract documentation from class properties.
	 *
	 * @param Node\Stmt\Class_ $node      The class node.
	 * @param string           $file_path The relative path to the file.
	 * @return array Property documentation keyed by property name.
	 */
	private function extract_property_docs( Node\Stmt\Class_ $node, $file_path ) {
		$properties = array();

		foreach ( $node->getProperties() as $property ) {
			$doc = $property->getDocComment();
			if ( $doc ) {
				foreach ( $property->props as $prop ) {
					$properties[ $prop->name->toString() ] = $this->format_doc_block( $doc );
				}
			} else {
				foreach ( $property->props as $prop ) {
					$this->track_undocumented( $file_path, 'property', $prop->name->toString() );
				}
			}
		}

		return $properties;
	}

	/**
	 * Extract documentation from class methods.
	 *
	 * @param Node\Stmt\Class_ $node      The class node.
	 * @param string           $file_path The relative path to the file.
	 * @return array Method documentation keyed by method name.
	 */
	private function extract_method_docs( Node\Stmt\Class_ $node, $file_path ) {
		$methods = array();

		foreach ( $node->getMethods() as $method ) {
			$doc = $method->getDocComment();
			if ( $doc ) {
				$methods[ $method->name->toString() ] = $this->format_doc_block( $doc );
			} else {
				$this->track_undocumented( $file_path, 'method', $method->name->toString() );
			}
		}

		return $methods;
	}

	/**
	 * Extract WordPress hooks from an AST node.
	 *
	 * @param Node   $node      The AST node to check.
	 * @param string $file_path The relative path to the file.
	 * @return array Extracted hook documentation.
	 */
	private function extract_hooks( $node, $file_path ) {
		$hooks = array();

		if ( $node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name ) {
			$function_name = $node->name->toString();

			if ( in_array( $function_name, $this->hook_functions, true ) ) {
				if ( isset( $node->args[0] ) && $node->args[0]->value instanceof Node\Scalar\String_ ) {
					$hook_name = $node->args[0]->value->value;
					$doc       = '';
					if ( $node->getAttribute( 'comments' ) ) {
						foreach ( $node->getAttribute( 'comments' ) as $comment ) {
							if ( $comment instanceof \PhpParser\Comment\Doc ) {
								$doc = $this->format_doc_block( $comment );
								break;
							}
						}
					}

					if ( ! $doc ) {
						$this->track_undocumented( $file_path, 'hook', $hook_name );
					}

					$hooks[] = array(
						'type'     => $this->hook_type_map[ $function_name ],
						'name'     => $hook_name,
						'doc'      => $doc,
						'function' => $function_name,
					);
				}
			}
		}

		// Recursively check child nodes
		foreach ( $node->getSubNodeNames() as $node_name ) {
			$sub_node = $node->$node_name;
			if ( $sub_node instanceof Node || is_array( $sub_node ) ) {
				if ( is_array( $sub_node ) ) {
					foreach ( $sub_node as $sub_sub_node ) {
						if ( $sub_sub_node instanceof Node ) {
							$hooks = array_merge( $hooks, $this->extract_hooks( $sub_sub_node, $file_path ) );
						}
					}
				} else {
					$hooks = array_merge( $hooks, $this->extract_hooks( $sub_node, $file_path ) );
				}
			}
		}

		return $hooks;
	}

	/**
	 * Format a docblock comment by removing comment markers.
	 *
	 * @param \PhpParser\Comment\Doc $doc The docblock comment.
	 * @return string Cleaned documentation text.
	 */
	private function format_doc_block( $doc ) {
		$text = $doc->getText();
		// Clean up the doc block
		$text = preg_replace( '/^\s*\/\*\*\s*/m', '', $text );
		$text = preg_replace( '/^\s*\*\s*/m', '', $text );
		$text = preg_replace( '/\s*\*\/\s*$/', '', $text );
		$text = preg_replace( '/\s*\/\s*$/', '', $text );
		$text = preg_replace( '/\n\s*$/', '', $text );

		$text = trim( $text );

		// Normalize URL formatting - remove angle brackets
		$text = preg_replace( '/<(https?:\/\/[^>]+)>/', '$1', $text );

		// Convert @param, @return, @since, etc. into list items
		$text = preg_replace(
			'/^@(param|return|since|date|deprecated|var|package|type)\s+/m',
			'* @$1 ',
			$text
		);

		return $text;
	}

	/**
	 * Track an undocumented code element.
	 *
	 * @param string $file The file path where the element was found.
	 * @param string $type The type of element (class, method, property, etc.).
	 * @param string $name The name of the undocumented element.
	 */
	private function track_undocumented( $file, $type, $name ) {
		if ( ! isset( $this->undocumented_elements[ $file ] ) ) {
			$this->undocumented_elements[ $file ] = array();
		}
		if ( ! isset( $this->undocumented_elements[ $file ][ $type ] ) ) {
			$this->undocumented_elements[ $file ][ $type ] = array();
		}
		$this->undocumented_elements[ $file ][ $type ][] = $name;
	}

	/**
	 * Generate index files for each directory of documentation.
	 */
	private function generate_index_files() {
		foreach ( $this->files_by_directory as $directory => $files ) {
			if ( empty( $files ) ) {
				continue;
			}

			sort( $files ); // Sort files for consistent order
			$index_path = $this->output_dir . '/' . $directory . '/index.md';
			$index_dir  = dirname( $index_path );

			if ( ! is_dir( $index_dir ) ) {
				mkdir( $index_dir, 0755, true );
			}

			// Generate title from directory name
			if ( '' === $directory || '.' === $directory ) {
				$index_path = $this->output_dir . '/index.md';
			}
			$title = $this->format_directory_title( $directory );

			$markdown = "# {$title}\n\n";

			foreach ( $files as $file ) {
				if ( 'index.md' === $file ) {
					continue;
				}
				$name = basename( $file, self::MD_EXT );
				$name = $this->format_title( $name );
				// Remove the '# ' prefix from the title for the link text
				$name      = preg_replace( '/^# /', '', $name );
				$markdown .= "* [{$name}]({$file})\n";
			}

			file_put_contents( $index_path, rtrim( $markdown ) . "\n" );
		}
	}

	/**
	 * Generate the META.md file containing undocumented elements and duplicate hooks.
	 */
	private function generate_meta_file() {
		if ( empty( $this->undocumented_elements ) ) {
			return;
		}

		$markdown  = "# Undocumented Code Elements\n\n";
		$markdown .= "This file tracks code elements that need documentation.\n\n";

		// Track duplicate hooks
		$hook_counts = array();
		foreach ( $this->files_by_directory['hooks'] ?? array() as $hook_file ) {
			$file_path = $this->output_dir . '/hooks/' . $hook_file;
			if ( file_exists( $file_path ) ) {
				$content = file_get_contents( $file_path );
				preg_match_all( '/^## `([^`]+)`/m', $content, $matches );
				foreach ( $matches[1] as $hook_name ) {
					if ( ! isset( $hook_counts[ $hook_name ] ) ) {
						$hook_counts[ $hook_name ] = 0;
					}
					++$hook_counts[ $hook_name ];
				}
			}
		}

		// Add duplicate hooks section if any found
		$duplicate_hooks = array_filter(
			$hook_counts,
			function ( $count ) {
				return $count > 1;
			}
		);

		if ( ! empty( $duplicate_hooks ) ) {
			$markdown .= "# Duplicate Hooks\n\n";
			arsort( $duplicate_hooks ); // Sort by count, highest first

			foreach ( $duplicate_hooks as $hook => $count ) {
				$markdown .= "- `{$hook}` (used {$count} times)\n";
			}
			$markdown .= "\n";
		}

		// Sort files alphabetically
		ksort( $this->undocumented_elements );

		foreach ( $this->undocumented_elements as $file => $types ) {
			$markdown .= '## ' . $file . "\n\n";

			// Sort types of elements(class, method, property, hook, function).
			ksort( $types );

			foreach ( $types as $type => $elements ) {
				$markdown .= '### ' . ucfirst( $type ) . "s\n\n";

				// Sort elements alphabetically
				sort( $elements );

				foreach ( $elements as $element ) {
					$markdown .= '- `' . $element . "`\n";
				}
				$markdown .= "\n";
			}
		}

		file_put_contents( $this->output_dir . '/META.md', $markdown );
	}

	/**
	 * Save markdown documentation to appropriate files.
	 *
	 * @param \Symfony\Component\Finder\SplFileInfo $file The source file.
	 * @param array                                 $docs The documentation to save.
	 */
	private function save_markdown( $file, $docs ) {
		if ( empty( $docs ) ) {
			return;
		}

		// Handle hooks separately
		if ( ! empty( $docs['hooks'] ) ) {
			$markdown = '';
			// Group hooks by type
			$grouped_hooks = array();
			foreach ( $docs['hooks'] as $hook ) {
				$type = $hook['type'];
				if ( ! isset( $grouped_hooks[ $type ] ) ) {
					$grouped_hooks[ $type ] = array();
				}
				$grouped_hooks[ $type ][] = $hook;
			}

			// Create hooks directory if it doesn't exist
			$hooks_dir = $this->output_dir . '/hooks';
			if ( ! is_dir( $hooks_dir ) ) {
				mkdir( $hooks_dir, 0755, true );
			}

			// Save each hook type to its own file
			foreach ( $grouped_hooks as $type => $hooks ) {
				$hook_file = $hooks_dir . '/' . $type . '.md';

				// Track hooks directory for index generation
				if ( ! isset( $this->files_by_directory['hooks'] ) ) {
					$this->files_by_directory['hooks'] = array();
				}
				if ( ! in_array( basename( $hook_file ), $this->files_by_directory['hooks'], true ) ) {
					$this->files_by_directory['hooks'][] = basename( $hook_file );
				}

				// Get existing content and parse existing hooks with their sources
				$hook_markdown  = '';
				$existing_hooks = array();

				if ( file_exists( $hook_file ) ) {
					$existing_content = file_get_contents( $hook_file );
					// Parse existing hooks and their sources from the content
					preg_match_all( '/^## `([^`]+)`\n\n(.*?)(?=\n## |$)/ms', $existing_content, $matches, PREG_SET_ORDER );
					foreach ( $matches as $match ) {
						$hook_name                    = $match[1];
						$content                      = trim( $match[2] );
						$existing_hooks[ $hook_name ] = $content;
					}
					$hook_markdown = $existing_content;
				} else {
					// Only add title for new files
					$hook_markdown = '# ' . ucfirst( str_replace( '_', ' ', $type ) ) . "\n\n";
				}

				$updated_hooks = array();
				foreach ( $hooks as $hook ) {
					$source       = str_replace( dirname( dirname( __DIR__ ) ) . '/includes/', '', $file->getRealPath() );
					$hook_content = '';

					if ( ! empty( $hook['doc'] ) ) {
						$hook_content .= "{$hook['doc']}\n\n";
					}
					$hook_content .= "### Source Files\n\n";
					$hook_content .= '* ' . $source . "\n\n";

					if ( isset( $existing_hooks[ $hook['name'] ] ) ) {
						// If hook exists, append the new source file if not already present
						if ( strpos( $existing_hooks[ $hook['name'] ], $source ) === false ) {
							$existing_hooks[ $hook['name'] ] = preg_replace(
								'/(### Source Files\n\n.*?)(\n\n|$)/s',
								"$1* $source\n\n",
								$existing_hooks[ $hook['name'] ]
							);
						}
						$updated_hooks[ $hook['name'] ] = true;
						continue;
					}

					$hook_markdown                 .= "## `{$hook['name']}`\n\n" . $hook_content;
					$updated_hooks[ $hook['name'] ] = true;
				}

				// Keep only hooks that were updated or added
				$final_markdown = '';
				if ( file_exists( $hook_file ) ) {
					$lines        = explode( "\n", $hook_markdown );
					$current_hook = null;
					$buffer       = '';

					foreach ( $lines as $line ) {
						if ( preg_match( '/^## `([^`]+)`$/', $line, $matches ) ) {
							if ( $current_hook && isset( $updated_hooks[ $current_hook ] ) ) {
								$final_markdown .= $buffer;
							}
							$current_hook = $matches[1];
							$buffer       = $line . "\n";
						} else {
							$buffer .= $line . "\n";
						}
					}
					// Handle the last hook
					if ( $current_hook && isset( $updated_hooks[ $current_hook ] ) ) {
						$final_markdown .= $buffer;
					}

					$hook_markdown = $final_markdown ? $final_markdown : $hook_markdown;
				}

				file_put_contents( $hook_file, $hook_markdown );
			}

			// Remove hooks from docs array so they're not included in the main file
			unset( $docs['hooks'] );
		}

		// Handle regular documentation
		if ( ! empty( $docs ) ) {
			// Keep the original path structure
			$relative_path = str_replace(
				dirname( dirname( __DIR__ ) ) . '/includes/',
				'',
				$file->getRealPath()
			);

			// Add -file suffix before .md extension
			$output_path = $this->output_dir . '/' . preg_replace(
				'/' . self::PHP_EXT . '$/',
				self::FILE_SUFFIX . self::MD_EXT,
				$relative_path
			);

			$output_dir = dirname( $output_path );
			if ( ! is_dir( $output_dir ) ) {
				mkdir( $output_dir, 0755, true );
			}

			$markdown = $this->generate_markdown( $docs, $relative_path );
			file_put_contents( $output_path, $markdown );

			// Track files by directory for index generation
			$relative_dir = dirname( $relative_path );
			if ( ! isset( $this->files_by_directory[ $relative_dir ] ) ) {
				$this->files_by_directory[ $relative_dir ] = array();
			}
			$this->files_by_directory[ $relative_dir ][] = basename( $output_path );
		}
	}

	/**
	 * Generate markdown content from documentation array.
	 *
	 * @param array  $docs         Documentation organized by type.
	 * @param string $source_path  The relative path to the source file.
	 * @return string Generated markdown content.
	 */
	private function generate_markdown( $docs, $source_path ) {
		$markdown = '';

		// Generate standalone functions documentation
		if ( ! empty( $docs['functions'] ) ) {
			// Convert file path to title
			// e.g., "api/api-helpers.php" becomes "API Helpers"
			$file_title = basename( $source_path, self::PHP_EXT );
			$file_title = str_replace( '-', ' ', $file_title );
			$file_title = ucwords( $file_title );
			// Special handling for "api" to become "API"
			$file_title = preg_replace( '/\b[Aa]pi\b/', 'API', $file_title );
			$markdown  .= $this->format_title( $file_title . ' Global Functions' ) . "\n\n";
			foreach ( $docs['functions'] as $name => $doc ) {
				$markdown .= '## `' . $name . '()`' . "\n\n";
				$markdown .= trim( $doc ) . "\n\n\n";  // Add extra newline after each function
			}
			$markdown .= "---\n\n";
		}

		// Handle class documentation
		if ( isset( $docs['class'] ) ) {
			$markdown .= $this->format_title( $docs['class']['name'] ) . "\n\n";
			$markdown .= trim( $docs['class']['doc'] ) . "\n\n";

			// Add properties section
			if ( ! empty( $docs['class']['properties'] ) ) {
				$markdown .= '## Properties' . "\n\n";
				foreach ( $docs['class']['properties'] as $name => $doc ) {
					$markdown .= '### `$' . $name . '`' . "\n\n";
					$markdown .= trim( $doc ) . "\n\n";
				}
			}

			if ( ! empty( $docs['class']['methods'] ) ) {
				$markdown .= '## Methods' . "\n\n";
				foreach ( $docs['class']['methods'] as $name => $doc ) {
					$markdown .= '### `' . $name . '`' . "\n\n";
					$markdown .= trim( $doc ) . "\n\n";
				}
			}
		}

		// Ensure file ends with single newline and no trailing spaces
		$markdown = rtrim( rtrim( $markdown ), "\n" ) . "\n";
		return $markdown;
	}

	/**
	 * Format a title for markdown documentation.
	 *
	 * @param string $title The title to format.
	 * @return string Formatted title.
	 */
	private function format_title( $title ) {
		// Special case handling for common terms
		$title = str_replace(
			array(
				'rest-api',
				'acf-rest-api',
				'class-acf-rest',
				'-file',
			),
			array(
				'REST API',
				'ACF REST API',
				'Class ACF REST',
				'',
			),
			$title
		);

		// Convert to title case and handle special words
		$title = ucwords( str_replace( '-', ' ', $title ) );

		// Convert "Api" or "api" to "API"
		$title = preg_replace( '/\b[Aa]pi\b/', 'API', $title );

		return '# ' . $title;
	}

	/**
	 * Format directory title with special cases.
	 *
	 * @param string $directory Directory name.
	 * @return string Formatted title.
	 */
	private function format_directory_title( $directory ) {
		if ( '' === $directory || '.' === $directory ) {
			return 'Code Reference';
		}

		if ( 'rest-api' === $directory ) {
			return 'REST API';
		}

		$title = basename( $directory );
		$title = str_replace( '-', ' ', $title );
		$title = ucwords( $title );
		$title = preg_replace( '/\b[Aa]pi\b/', 'API', $title );

		return $title;
	}
}

// Parse command line arguments
$options    = getopt( '', array( 'output::' ) );
$output_dir = isset( $options['output'] ) ? $options['output'] : __DIR__ . '/../code-reference';
$generator  = new DocGenerator( $output_dir );
$generator->generate();
