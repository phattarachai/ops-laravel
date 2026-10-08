<?php

declare(strict_types=1);

return [
    'kind' => [
        'opened' => 'เริ่มเกิด',
        'escalated' => 'รุนแรงขึ้น',
        'reminder' => 'ยังเกิดอยู่',
        'resolved' => 'หายแล้ว',
        'test' => 'ทดสอบ',
    ],
    'severity' => [
        'warning' => 'เตือน',
        'critical' => 'วิกฤต',
    ],
    'subject' => '[:severity] :rule — :key',
    'value' => [
        'threshold' => ':value ครั้งใน :window',
        'rate' => ':value% ใน :window',
        'absence' => 'เงียบไป :window',
        'state' => ':level',
    ],
    'opened_at' => 'เริ่ม :time',
    'resolved_at' => 'หาย :time',
    'peak' => 'สูงสุด :value',
    'open_dashboard' => 'เปิดใน Ops',
    'test_subject' => 'ทดสอบการแจ้งเตือน Ops',
    'test_body' => 'ถ้าเห็นข้อความนี้ แปลว่าช่องทาง :channel ใช้งานได้',
    'minutes' => ':count นาที',
    'seconds' => ':count วินาที',
];
