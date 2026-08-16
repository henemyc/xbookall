<?php

return [
    'project_id' => env('FCM_PROJECT_ID', 'gymxbookapp'),
    // Set FCM_SERVICE_ACCOUNT_PATH in production. A relative value such as
    // storage/app/private/firebase/gymxbook-production-fcm.json is resolved
    // from Laravel base_path and must remain outside public/.
    'service_account_path' => env('FCM_SERVICE_ACCOUNT_PATH', 'storage/app/private/firebase/gymxbook-production-fcm.json'),
];
