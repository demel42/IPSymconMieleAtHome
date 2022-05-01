<?php

declare(strict_types=1);

eval('
declare(strict_types=1);
namespace MieleAtHome {
?>'
. preg_replace('/declare\(strict_types=1\);/', '', file_get_contents(__DIR__ . '/../libs/CommonStubs/common.php'))
. '
}
');
