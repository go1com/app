App console
====

```php
<?php

namespace me\app;

use Symfony\Component\Console\Application;

/** @var App $app */
$app = require __DIR__ . '/public/index.php';
$console = new Application($app::NAME, $app::VERSION);
$console->add($app['cmd.import']);
$console->run();
```
