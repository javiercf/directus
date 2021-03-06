<?php

namespace Directus\Exception;

use ErrorException;
use Directus\Exception\FatalThrowableError;
use Directus\Hook\Hook;

class ExceptionHandler
{
    public function __construct()
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     *
     * @param  int  $level
     * @param  string  $message
     * @param  string  $file
     * @param  int  $line
     * @param  array  $context
     * @return void
     *
     * @throws \ErrorException
     */
    public function handleError($level, $message, $file = '', $line = 0, $context = [])
    {
        if (error_reporting() & $level) {
            $e = new ErrorException($message, 0, $level, $file, $line);
            Hook::run('application.error', $e);
        }
    }

    /**
     * Handle an uncaught exception
     *
     * @param  \Throwable  $e
     * @return void
     */
    public function handleException($e)
    {
        if (! $e instanceof Exception) {
            $e = new FatalThrowableError($e);
        }

        if('production' !== DIRECTUS_ENV) {
            $this->render($e);
        }

        Hook::run('application.error', $e);
    }

    /**
     * Handle the PHP shutdown event.
     *
     * @return void
     */
    public function handleShutdown()
    {
        if (! is_null($error = error_get_last()) && $this->isFatal($error['type'])) {
            Hook::run('application.error', $e);
        }
    }

    /**
     * Determine if the error type is fatal.
     *
     * @param  int  $type
     * @return bool
     */
    protected function isFatal($type)
    {
        return in_array($type, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]);
    }

    public function render($e)
    {
        $content = [];
        $trace = $e->getTrace();

        $content[] = '<p class="error_backtrace">';
        $content[] = '<ol>';
        foreach($trace as $item)
            $content[] = '<li>' . (isset($item['file']) ? $item['file'] : '<unknown file>') . ' ' . (isset($item['line']) ? $item['line'] : '<unknown line>') . ' calling ' . $item['function'] . '()</li>';
        $content[] = '</ol>';
        $content[] = '</div>';

        $content = implode(PHP_EOL, $content);

        echo $this->getExceptionContent($content);
    }

    public function getExceptionContent($content)
    {
        return <<<EOF
<!DOCTYPE html>
<html>
    <head>
        <meta name="robots" content="noindex,nofollow" />
        <style>
            html { background: #eee; padding: 10px }
        </style>
    </head>
    <body>
        $content
    </body>
</html>
EOF;
    }
}