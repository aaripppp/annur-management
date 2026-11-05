<?php

return [
    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => ['required', 'file', 'max:12288'],
        'directory' => 'livewire-tmp',
        'middleware' => 'throttle:60,1',
        'preview_mimes' => ['jpg', 'jpeg', 'png'],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],
];
