<?php

return [
    'node_binary' => env('SOCIAL_NODE_BINARY', 'node'),
    'chromium_binary' => env('SOCIAL_CHROMIUM_BINARY'),
    'daily_output_token_budget' => 20000,
    'engagement_components' => ['reactions', 'comments', 'shares', 'clicks'],
    'score_weights' => ['new_followers' => 20, 'engagement_rate' => 30, 'impressions' => 20, 'clicks' => 10, 'posts' => 10, 'engagements' => 10],
    'score_thresholds' => ['excellent' => 90, 'good' => 75, 'average' => 60, 'needs_attention' => 40, 'critical' => 0],
    'attention_ratio' => 0.5,
    'max_import_rows' => 1000,
];
