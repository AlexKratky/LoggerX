# LoggerX

Class to work with logs.

### Installation

`composer require alexkratky/loggerx`

### Logging the data

Writing data to log using panx-framework is quite easy. All you need to do, is calling function `Logger::log($text, $file)`, where `$text` is the text that you want to log and `$file` is the name of log. The second parameter is optional, and if you do not pass any data, Logger Class will use `main.log` as default log file name.

This function returns the number of bytes that were written to the file, or **FALSE** on failure. So if you want to check, if the data was written successfully, you can do it by:

```php
require 'vendor/autoload.php';

use AlexKratky\Logger;
use AlexKratky\Cache;

Logger::setDirectory(__DIR__ . '/logs/');
Cache::setDirectory(__DIR__ . '/logs/');

if(Logger::log("text") !== false) {
    // data was written successfully
} else {
    // data was not written successfully
}
```

