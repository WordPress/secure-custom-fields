/**
 * Custom Post Type Commands
 *
 * Dynamic commands for user-created custom post types in Secure Custom Fields.
 * This file generates navigation commands for each registered post type that
 * the current user has access to, creating "View All", "Add New", and "Edit" commands.
 *
 * @since SCF 6.5.0
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { dispatch, resolveSelect, select, useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';
import { page, plus, edit } from '@wordpress/icons';

/**
 * Maximum number of posts a search loader surfaces per post type.
 */
const SEARCH_RESULTS_LIMIT = 10;

/**
 * Creates a command-loader hook bound to a single post type.
 *
 * The hook is reused by the command palette, which calls it on every search
 * change, so it must follow the rules of hooks (no async). Posts are fetched
 * through the core-data store, which also resolves read capabilities
 * server-side.
 *
 * @param {Object} postType Post type object from the REST types endpoint.
 *
 * @return {Function} Loader hook accepting `{ search }` and returning commands.
 */
const createPostSearchLoaderHook = ( postType ) =>
	function usePostSearchLoader( { search } ) {
		const { records, isLoading } = useSelect(
			( selectStore ) => {
				const { getEntityRecords, hasFinishedResolution } =
					selectStore( 'core' );
				const query = {
					search: search ? search : undefined,
					per_page: SEARCH_RESULTS_LIMIT,
					orderby: search ? 'relevance' : 'date',
				};
				return {
					records: getEntityRecords(
						'postType',
						postType.slug,
						query
					),
					isLoading: ! hasFinishedResolution( 'getEntityRecords', [
						'postType',
						postType.slug,
						query,
					] ),
				};
			},
			[ search ]
		);

		const commands = useMemo( () => {
			return ( records ?? [] )
				.slice( 0, SEARCH_RESULTS_LIMIT )
				.map( ( record ) => {
					const editPostUrl = addQueryArgs( 'post.php', {
						post: record.id,
						action: 'edit',
					} );
					return {
						name: `scf/edit-post-${ postType.slug }-${ record.id }`,
						label:
							record.title?.rendered ||
							__( '(no title)', 'secure-custom-fields' ),
						icon: edit,
						category: 'edit',
						keywords: [
							'edit',
							'modify',
							postType.slug,
							postType.name,
						],
						callback: ( { close } ) => {
							document.location = editPostUrl;
							close();
						},
					};
				} );
		}, [ records ] );

		return {
			commands,
			isLoading,
		};
	};

/**
 * Register custom post type commands
 */
const registerPostTypeCommands = async () => {
	if ( ! resolveSelect( 'core' ) || ! dispatch( 'core/commands' ) ) {
		return;
	}

	const postTypes = await resolveSelect( 'core' ).getPostTypes( {
		per_page: -1,
		source: 'scf',
	} );

	const commandStore = dispatch( 'core/commands' );
	const registeredCommands = select( 'core/commands' ).getCommands();

	postTypes.forEach( ( postType ) => {
		if ( ! postType?.visibility?.show_ui ) {
			return;
		}

		// WordPress core does NOT expose `visibility.show_in_rest` on the
		// /wp/v2/types response. The canonical "REST is on" signal is a
		// non-empty `rest_base`. A non-empty `rest_namespace` confirms the
		// post type is registered against a real REST namespace (e.g. wp/v2).
		// Check both — a CPT with `rest_base` set but no namespace is
		// malformed and would 404 on every request.
		const hasRestSupport =
			!! postType.rest_base && !! postType.rest_namespace;

		// eslint-disable-next-line no-console
		console.debug( '[SCF commands] registering for', postType.slug, {
			show_in_rest: hasRestSupport,
			rest_base: postType.rest_base,
		} );

		// Wrap each CPT registration so one failure doesn't kill the rest and
		// so we can see which post type silently fails to register.
		const registerOne = async () => {
			const viewAllCommandUrl = addQueryArgs( 'edit.php', {
				post_type: postType.slug,
			} );

			// WordPress stores destination URLs in the command *name*, appended to
			// the menu slug (which is also a relative URL), resulting in somewhat
			// peculiar naming, e.g.
			// edit.php?post_type=movie-post-new.php?post_type=movie
			if (
				! registeredCommands.some( ( cmd ) =>
					cmd.name.endsWith( viewAllCommandUrl )
				) &&
				( await resolveSelect( 'core' ).canUser(
					'read',
					postType.rest_base
				) )
			) {
				// Register "View All" command for this post type
				commandStore.registerCommand( {
					name: `scf/cpt-${ postType.slug }`,
					label: postType.labels.all_items,
					icon: page,
					keywords: [
						'post type',
						'content',
						'cpt',
						postType.slug,
						postType.name,
					].filter( Boolean ),
					callback: ( { close } ) => {
						document.location = viewAllCommandUrl;
						close();
					},
				} );
			}

			const addNewCommandUrl = addQueryArgs( 'post-new.php', {
				post_type: postType.slug,
			} );

			if (
				! registeredCommands.some( ( cmd ) =>
					cmd.name.endsWith( addNewCommandUrl )
				) &&
				( await resolveSelect( 'core' ).canUser(
					'create',
					postType.rest_base
				) )
			) {
				// Register "Add New" command for this post type
				commandStore.registerCommand( {
					name: `scf/new-${ postType.slug }`,
					label: postType.labels.add_new_item,
					icon: plus,
					keywords: [
						'add',
						'new',
						'create',
						'content',
						postType.slug,
						postType.name,
					],
					callback: ( { close } ) => {
						document.location = addNewCommandUrl;
						close();
					},
				} );
			}

			// Register "Edit Post Type" command. The scf_post_id field is only
			// exposed to users who can edit the post type definition, so its
			// presence gates the command.
			if ( postType.scf_post_id ) {
				commandStore.registerCommand( {
					name: `scf/edit-${ postType.slug }`,
					label: sprintf(
						/* translators: %s: post type label */
						__( 'Edit post type: %s', 'secure-custom-fields' ),
						postType.name
					),
					icon: edit,
					keywords: [
						'edit',
						'modify',
						'post type',
						'cpt',
						'settings',
						postType.slug,
						postType.name,
					],
					callback: ( { close } ) => {
						document.location = addQueryArgs( 'post.php', {
							post: postType.scf_post_id,
							action: 'edit',
						} );
						close();
					},
				} );
			}

			// Register a loader that searches this post type's posts as the user
			// types, letting them pick an existing instance to edit. The core
			// /wp/v2/types response uses a non-empty `rest_base` as the canonical
			// "show in REST" signal — there is no `visibility.show_in_rest` key.
			if ( hasRestSupport ) {
				commandStore.registerCommandLoader( {
					name: `scf/edit-posts-${ postType.slug }`,
					hook: createPostSearchLoaderHook( postType ),
				} );
			}
		};

		registerOne().catch( ( err ) => {
			// eslint-disable-next-line no-console
			console.error(
				'[SCF commands] failed to register for',
				postType.slug,
				err
			);
		} );
	} );
};

/**
 * Register taxonomy commands for SCF-managed taxonomies.
 */
const registerTaxonomyCommands = async () => {
	if ( ! resolveSelect( 'core' ) || ! dispatch( 'core/commands' ) ) {
		return;
	}

	const taxonomies = await resolveSelect( 'core' ).getTaxonomies( {
		per_page: -1,
	} );

	const commandStore = dispatch( 'core/commands' );
	const registeredCommands = select( 'core/commands' ).getCommands();

	taxonomies.forEach( ( taxonomy ) => {
		// Skip taxonomies not managed by SCF.
		if ( ! taxonomy.scf_taxonomy_id ) {
			return;
		}

		// Wrap registration so we can await capability checks.
		const registerOne = async () => {
			const viewTermsUrl = addQueryArgs( 'edit-tags.php', {
				taxonomy: taxonomy.slug,
			} );

			if (
				! registeredCommands.some( ( cmd ) =>
					cmd.name.endsWith( viewTermsUrl )
				) &&
				( await resolveSelect( 'core' ).canUser(
					'edit',
					'taxonomy',
					taxonomy.slug
				) )
			) {
				commandStore.registerCommand( {
					name: `scf/tax-${ taxonomy.slug }`,
					label: taxonomy.name,
					icon: page,
					keywords: [
						'taxonomy',
						'terms',
						'tags',
						'categories',
						taxonomy.slug,
					].filter( Boolean ),
					callback: ( { close } ) => {
						document.location = viewTermsUrl;
						close();
					},
				} );
			}

			// Register "Edit Taxonomy" definition command.
			commandStore.registerCommand( {
				name: `scf/edit-tax-${ taxonomy.slug }`,
				label: sprintf(
					/* translators: %s: taxonomy label */
					__( 'Edit taxonomy: %s', 'secure-custom-fields' ),
					taxonomy.name
				),
				icon: edit,
				keywords: [
					'edit',
					'modify',
					'taxonomy',
					'settings',
					taxonomy.slug,
					taxonomy.name,
				],
				callback: ( { close } ) => {
					document.location = addQueryArgs( 'post.php', {
						post: taxonomy.scf_taxonomy_id,
						action: 'edit',
					} );
					close();
				},
			} );
		};

		registerOne().catch( ( err ) => {
			// eslint-disable-next-line no-console
			console.error(
				'[SCF commands] failed to register taxonomy for',
				taxonomy.slug,
				err
			);
		} );
	} );
};

if ( 'requestIdleCallback' in window ) {
	window.requestIdleCallback( registerPostTypeCommands, { timeout: 500 } );
	window.requestIdleCallback( registerTaxonomyCommands, { timeout: 500 } );
} else {
	setTimeout( registerPostTypeCommands, 500 );
	setTimeout( registerTaxonomyCommands, 500 );
}
