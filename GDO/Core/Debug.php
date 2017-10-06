<?php
namespace GDO\Core;
use GDO\UI\GDT_Page;
use GDO\Util\Common;
use GDO\Mail\Mail;

/**
 * Debug backtrace and error handler.
 * Can send email on PHP errors.
 * Also has a method to get debug timings.
 * 
 * @example Debug::enableErrorHandler(); fatal_ooops();
 * @todo it displays and sends two errors for each error
 * @author gizmore
 * @version 3.02
 */
final class Debug
{
    private static $die = true;
    private static $enabled = false;
    private static $exception = false;
    private static $MAIL_ON_ERROR = true;
    
    // Call this to auto inc.
    public static function init()
    {
    }
    
    // ###############
    // ## Settings ###
    // ###############
    public static function setDieOnError($bool = true)
    {
        self::$die = $bool;
    }
    public static function setMailOnError($bool = true)
    {
        self::$MAIL_ON_ERROR = $bool;
    }
    public static function disableErrorHandler()
    {
        if (self::$enabled)
        {
            restore_error_handler();
            self::$enabled = false;
        }
    }
    public static function enableErrorHandler()
    {
        if (!self::$enabled)
        {
            set_error_handler(array(
                'GDO\\Core\\Debug',
                'error_handler'));
            register_shutdown_function(array(
                'GDO\\Core\\Debug',
                'shutdown_function'));
            self::$enabled = true;
        }
    }
    public static function enableStubErrorHandler()
    {
        self::disableErrorHandler();
        set_error_handler(array(
            'GDO\\Core\\Debug',
            'error_handler_stub'));
        self::$enabled = true;
    }
    
    #####################
    ## Error Handlers ###
    #####################
    public static function error_handler_stub($errno, $errstr, $errfile, $errline, $errcontext)
    {
        return false;
    }
    
    /**
     * This one get's called on a fatal.
     * No stacktrace available and some vars are messed up.
     */
    public static function shutdown_function()
    {
//         if (error_reporting())
//         {
            if (self::$enabled)
            {
                $error = error_get_last();
//                 var_dump($error);
                if ($error['type'] === 1)
                {
                    self::error_handler(1, $error['message'], self::shortpath($error['file']), $error['line'], NULL);
                }
            }
//         }
    }
    
    /**
     * Error handler creates some html backtrace and can die on _every_ warning etc.
     * 
     * @param int $errno            
     * @param string $errstr            
     * @param string $errfile            
     * @param int $errline            
     * @param
     *            $errcontext
     * @return false
     */
    public static function error_handler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        if (!error_reporting())
        {
            return;
        }
        
        // Log as critical!
        if (class_exists('GDO\Core\Logger', false))
        {
            Logger::logCritical(sprintf('%s in %s line %s', $errstr, $errfile, $errline));
            Logger::flush();
        }
        
        switch ($errno)
        {
            case - 1:
                $errnostr = 'GWF Error';
                break;
            
            case E_ERROR:
            case E_CORE_ERROR:
                $errnostr = 'PHP Fatal Error';
                break;
            case E_WARNING:
            case E_USER_WARNING:
            case E_CORE_WARNING:
                $errnostr = 'PHP Warning';
                break;
            case E_USER_NOTICE:
            case E_NOTICE:
                $errnostr = 'PHP Notice';
                break;
            case E_USER_ERROR:
                $errnostr = 'PHP Error';
                break;
            case E_STRICT:
                $errnostr = 'PHP Strict Error';
                break;
            // if(PHP5.3) case E_DEPRECATED: case E_USER_DEPRECATED: $errnostr = 'PHP Deprecated'; break;
            // if(PHP5.2) case E_RECOVERABLE_ERROR: $errnostr = 'PHP Recoverable Error'; break;
            case E_COMPILE_WARNING:
            case E_COMPILE_ERROR:
                $errnostr = 'PHP Compiling Error';
                break;
            case E_PARSE:
                $errnostr = 'PHP Parsing Error';
                break;
            
            default:
                $errnostr = 'PHP Unknown Error';
                break;
        }
        
        $is_html = (PHP_SAPI !== 'cli') && (Application::instance()->isHTML());
        
        if ($is_html)
        {
            $message = sprintf('<p>%s(EH %s):&nbsp;%s&nbsp;in&nbsp;<b style=\"font-size:16px;\">%s</b>&nbsp;line&nbsp;<b style=\"font-size:16px;\">%s</b></p>', $errnostr, $errno, $errstr, $errfile, $errline) . PHP_EOL;
        }
        else
        {
            $message = sprintf('%s(EH %s) %s in %s line %s.', $errnostr, $errno, $errstr, $errfile, $errline);
        }
        
        // Send error to admin
        if (self::$MAIL_ON_ERROR)
        {
            self::sendDebugMail(self::backtrace($message, false));
        }
        
        // Output error
        if (PHP_SAPI === 'cli')
        {
            file_put_contents('php://stderr', self::backtrace($message, false) . PHP_EOL);
        }
        else
        {
            $message = $is_html ? sprintf('<div class="gdo-exception">%s</div>', $message) : $message;
            $message = GWF_ERROR_STACKTRACE ? self::backtrace($message, $is_html) : $message;
            // echo $is_html? self::renderError($message) : $message;
            echo self::renderError($message);
        }
        
        if (self::$die)
        {
            die(1);
        }
        
        return true;
    }
    public static function exception_handler($e)
    {
        $is_html = PHP_SAPI !== 'cli';
        $firstLine = sprintf("%s in %s Line %s", $e->getMessage(), $e->getFile(), $e->getLine());
        
        $mail = self::$MAIL_ON_ERROR;
        $log = true;
        
        // Send error to admin?
        if ($mail)
        {
            self::sendDebugMail($firstLine . $e->getTraceAsString());
        }
        
        // Log it?
        if ($log)
        {
            Logger::logCritical($firstLine);
            Logger::flush();
        }
        
        // $content = ''; while (ob_get_level() > 0) { $content .= ob_get_contents(); ob_end_clean(); }
        $message = self::backtraceException($e, $is_html, ' (XH)');
        echo self::renderError($message);
        return true;
    }
    private static function renderError($message)
    {
        $isHTML = Application::instance()->isHTML();
        return defined('GWF_CORE_STABLE') && $isHTML ?
        GDT_Page::make()->html(GDT_Error::withHTML($message)->render())->render() :
        ($message . PHP_EOL);
    }
    public static function disableExceptionHandler()
    {
        if (self::$exception === true)
        {
            restore_exception_handler();
            self::$exception = false;
        }
    }
    public static function enableExceptionHandler()
    {
        if (!self::$exception)
        {
            set_exception_handler(array(
                'GDO\\Core\\Debug',
                'exception_handler'));
            self::$exception = true;
        }
    }
    
    /**
     * Send error report mail.
     * 
     * @param string $message            
     */
    public static function sendDebugMail($message)
    {
        return Mail::sendDebugMail(': PHP Error', $message);
    }
    
    /**
     * Get some additional information
     * 
     * @todo move?
     */
    public static function getDebugText($message)
    {
        $user = "~~USER~~";
        // try { $user = GDO_User::current()->displayName(); } catch (Exception $e) { $user = 'ERROR'; }
        $args = array(
            isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'NULL',
            isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : self::getMoMe(),
            isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'NULL',
            isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'NULL',
            isset($_SERVER['USER_AGENT']) ? $_SERVER['USER_AGENT'] : 'NULL',
            $user,
            $message,
            print_r($_GET, true),
            print_r($_POST, true),
            print_r($_COOKIE, true));
        $args = array_map('htmlspecialchars', $args);
        $pattern = "RequestMethod: %s\nRequestURI: %s\nReferer: %s\nIP: %s\nUserAgent: %s\nGDO_User: %s\n\nMessage: %s\n\n_GET: %s\n\n_POST: %s\n\n_COOKIE: %s\n\n";
        return vsprintf($pattern, $args);
    }
    private static function getMoMe()
    {
        return Common::getGetString('mo') . '/' . Common::getGetString('me');
    }
    
    /**
     * Return a backtrace in either HTML or plaintext.
     * You should use monospace font for html.
     * HTML means (x)html(5) and <pre> style.
     * Plaintext means nice for logfiles.
     * 
     * @param string $message            
     * @param boolean $html            
     * @return string
     */
    public static function backtrace($message = '', $html = true)
    {
        return self::backtraceMessage($message, $html, debug_backtrace());
    }
    public static function backtraceException($e, $html = true, $message = '')
    {
        $message = sprintf("PHP Exception$message: %s in %s line %s", $e->getMessage(), self::shortpath($e->getFile()), $e->getLine());
        return self::backtraceMessage($message, $html, $e->getTrace());
    }
    private static function backtraceArgs(array $args = null)
    {
        $out = [];
        if ($args)
        {
            foreach ($args as $arg)
            {
                $out[] = self::backtraceArg($arg);
            }
        }
        return implode(",", $out);
    }
    private static function backtraceArg($arg)
    {
        if ($arg === null)
        {
            return 'NULL';
        }
        elseif ($arg === true)
        {
            return 'true';
        }
        elseif ($arg === false)
        {
            return 'false';
        }
        elseif (is_string($arg) || is_array($arg))
        {
            $arg = json_encode($arg);
        }
        elseif (is_object($arg))
        {
            return get_class($arg);
        }
        else
        {
            $arg = json_encode($arg, 1);
        }
        $app = mb_strlen($arg) > 48 ? '…' : '';
        return mb_substr($arg, 0, 48) . $app;
    }
    private static function backtraceMessage($message, $html = true, array $stack)
    {
        $badformat = false;
        
        // Fix full path disclosure
        $message = self::shortpath($message);
        
        if (! GWF_ERROR_STACKTRACE)
        {
            return $html ? sprintf('<div class="gdo-exception">%s</div>', $message) : $message;
        }
        
        // Append PRE header.
        $back = $html ? "<div class=\"gdo-exception\">\n" : '';
        
        // Append general title message.
        if ($message !== '')
        {
            $back .= $html ? '<em>' . $message . '</em>' : $message;
        }
        
        $implode = [];
        $preline = 'Unknown';
        $prefile = 'Unknown';
        $longest = 0;
        $i = 0;
        
        foreach ($stack as $row)
        {
            if ($i ++ > 0)
            {
                $function = sprintf('%s%s(%s)', isset($row['class']) ? $row['class'] . $row['type'] : '', $row['function'], self::backtraceArgs(isset($row['args']) ? $row['args'] : null));
                $implode[] = array(
                    $function,
                    $prefile,
                    $preline);
                $len = strlen($function);
                $longest = max(array(
                    $len,
                    $longest));
            }
            $preline = isset($row['line']) ? $row['line'] : '?';
            $prefile = isset($row['file']) ? $row['file'] : '[unknown file]';
        }
        
        $copy = [];
        foreach ($implode as $imp)
        {
            list ($func, $file, $line) = $imp;
            $len = strlen($func);
            $func .= str_repeat('.', $longest - $len);
            $copy[] = sprintf('%s %s line %s.', $func, self::shortpath($file), $line);
        }
        
        $back .= $html ? '<hr/>' : "\n";
        $back .= sprintf('Backtrace starts in %s line %s.<br/>', self::shortpath($prefile), $preline) . "\n";
        $back .= implode("\n", array_reverse($copy));
        $back .= $html ? '</div>' : '';
        return $back;
    }
    
    /**
     * Strip full pathes so we don't have a full path disclosure.
     * 
     * @param string $path            
     * @return string
     */
    public static function shortpath($path)
    {
        $path = str_replace(GWF_PATH, '', $path);
        return trim($path, ' /');
    }
}
    