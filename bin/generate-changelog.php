<?php
/**
 * Generate changelog entry from milestone PRs
 * 
 * Usage: php bin/generate-changelog. php --milestone-title "6.8.1" --release-date "15 Jan 2026" --prs-file prs. json --output changelog-entry.txt
 */

$options = getopt('', [
    'milestone-title:',
    'release-date:',
    'prs-file:',
    'output: ',
]);

$milestone_title = $options['milestone-title'] ?? '';
$release_date = $options['release-date'] ?? date('d M Y');
$prs_file = $options['prs-file'] ??  '';
$output_file = $options['output'] ??  'php://stdout';

if (empty($prs_file) || ! file_exists($prs_file)) {
    fwrite(STDERR, "Error: PRs file not found\n");
    exit(1);
}

$prs = json_decode(file_get_contents($prs_file), true);

if ($prs === null) {
    fwrite(STDERR, "Error: Invalid JSON in PRs file\n");
    exit(1);
}

// Categorize PRs by label
$categorized = [
    'Features' => [],
    'Enhancements' => [],
    'Fixes' => [],
    'Documentation' => [],
    'Project Management' => [],
    'Other' => [],
];

$label_mapping = [
    '[Type] Feature' => 'Features',
    '[Type] Enhancement' => 'Enhancements',
    '[Type] Bug' => 'Fixes',
    'documentation' => 'Documentation',
    '[Type] Project Management' => 'Project Management',
];

foreach ($prs as $pr) {
    $category = 'Other';
    
    foreach ($pr['labels'] as $label) {
        $label_name = $label['name'];
        if (isset($label_mapping[$label_name])) {
            $category = $label_mapping[$label_name];
            break;
        }
    }
    
    $categorized[$category][] = $pr;
}

// Generate changelog entry
$changelog = "= {$milestone_title} =\n";
$changelog .= "*Release Date {$release_date}*\n\n";

foreach ($categorized as $category => $items) {
    if (empty($items)) {
        continue;
    }
    
    $changelog .= "*{$category}*\n\n";
    
    foreach ($items as $pr) {
        $title = $pr['title'];
        // Remove common prefixes
        $title = preg_replace('/^(Fix|Add|Update|Remove|Improve|Create|Delete|Refactor):\s*/i', '', $title);
        $title = ucfirst($title);
        
        $changelog .= "- {$title}.\n";
    }
    
    $changelog .= "\n";
}

// Write output
if ($output_file === 'php://stdout') {
    echo $changelog;
} else {
    file_put_contents($output_file, $changelog);
    fwrite(STDERR, "Changelog entry written to {$output_file}\n");
}
