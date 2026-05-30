<?php

/*
|--------------------------------------------------------------------------
| API Route Composition
|--------------------------------------------------------------------------
|
| The mobile API serves several clients. Keep route ownership split by
| client/domain while preserving the existing /api endpoint paths.
|
| - shared.php: authentication and APIs used by more than one client
| - point-of-sale.php: cashier/POS workflows
| - attendance.php: attendance mobile workflows
| - menu.php: public customer menu/web-order workflows
| - setup.php: local development setup helpers
|
*/

require __DIR__.'/api/shared.php';
require __DIR__.'/api/point-of-sale.php';
require __DIR__.'/api/attendance.php';
require __DIR__.'/api/menu.php';
require __DIR__.'/api/setup.php';
