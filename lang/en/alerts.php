<?php

declare(strict_types=1);

return [
    'kind' => [
        'opened' => 'Started',
        'escalated' => 'Escalated',
        'reminder' => 'Still happening',
        'resolved' => 'Resolved',
        'test' => 'Test',
    ],
    'severity' => [
        'warning' => 'Warning',
        'critical' => 'Critical',
    ],
    'subject' => '[:severity] :rule — :key',
    'value' => [
        'threshold' => ':value in :window',
        'rate' => ':value% in :window',
        'absence' => 'silent for :window',
        'state' => ':level',
    ],
    'opened_at' => 'Opened :time',
    'resolved_at' => 'Resolved :time',
    'peak' => 'Peak :value',
    'open_dashboard' => 'Open in Ops',
    'test_subject' => 'Ops test notification',
    'test_body' => 'If you can read this, the :channel channel works.',
    'minutes' => '{1} :count minute|[2,*] :count minutes',
    'seconds' => '{1} :count second|[2,*] :count seconds',
];
