<? php
/**
 * Update readme.txt with new changelog entry
 * 
 * Usage: php bin/update-readme-changelog.php --readme readme.txt --changelog-entry changelog-entry.txt --output readme.txt
 */

$options = getopt('', [
    'readme:',
    'changelog-entry:',
    'output:',
]);

$readme_file = $options['readme'] ?? 'readme.txt';
$changelog_entry_file = $options['changelog-entry'] ?? '';
$output_file = $options['output'] ?? 'readme. txt';

if (! file_exists($readme_file)) {
    fwrite(STDERR, "Error: readme. txt not found at {$readme_file}\n");
    exit(1);
}

if (!file_exists($changelog_entry_file)) {
    fwrite(STDERR, "Error: changelog entry file not found at {$changelog_entry_file}\n");
    exit(1);
}

$readme_content = file_get_contents($readme_file);
$changelog_entry = file_get_contents($changelog_entry_file);

// Find the changelog section
$pattern = '/(== Changelog ==\s*\n\n)/';

if (preg_match($pattern, $readme_content, $matches, PREG_OFFSET_CAPTURE)) {
    $insert_position = $matches[0][1] + strlen($matches[0][0]);
    
    // Insert new changelog entry
    $updated_content = substr_replace(
        $readme_content,
        $changelog_entry .  "\n",
        $insert_position,
        0
    );
    
    file_put_contents($output_file, $updated_content);
    echo "Successfully updated {$output_file}\n";
} else {
    fwrite(STDERR, "Error: Could not find Changelog section in readme.txt\n");
    exit(1);
}
