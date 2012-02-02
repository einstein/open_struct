<?php

namespace OpenStructTest {
    class AssetHelpers {
        static $extended_struct;

        static function extended($struct, $value = 'test') {
            self::$extended_struct = array($struct, $value);
        }

        function asset_path($path) {
            return '/assets/'.$path;
        }
    }

    class S3AssetHelpers {
        function asset_path($path) {
            return 'http://example.s3.amazonaws.com'.$this->super($path);
        }

        function bad_super() {
            return $this->super();
        }

        function invoke() {
            return true;
        }
    }

    class ErrorHandler {
        static function error_handler($number, $message, $file, $line, $context) {
            return 'test';
        }
    }
}

namespace {
    class OpenStructTest extends ztest\UnitTestCase {

        function setup() {
            $this->struct = new OpenStruct;
            $this->assets = __CLASS__.'\\AssetHelpers';
            $this->s3 = __CLASS__.'\\S3AssetHelpers';
            $class = $this->assets;
            $class::$extended_struct = null;
            $this->get_error_handler = (function() {
                $handler = set_error_handler(function () {});
                restore_error_handler();
                return $handler;
            });
        }

        // #__call

            function test_magic_call() {
                $struct = $this->struct->extend($this->assets);
                assert_equal('/assets/test.png', $this->struct->asset_path('test.png'));
                assert_throws('BadMethodCallException', function() use ($struct) { $struct->missing(); });
            }

        // #__construct

            function test_constructor_shoud_accept_properties() {
                $properties = array('test' => 'value');
                $struct = new OpenStruct(array('test' => 'value'));
                assert_equal($properties, $struct->properties);
            }

        // #__get/__set

            function test_get_and_set() {
                ensure(!isset($this->struct->properties['test']));
                $this->struct->test = 'testing';
                assert_equal('testing', $this->struct->test);
                assert_equal('testing', $this->struct->properties['test']);
            }

        // #__invoke/invoke

            function test_invoke() {
                $struct = $this->struct;
                assert_throws('BadMethodCallException', function() use ($struct) { $struct(); });
                $struct->extend($this->s3);
                ensure($struct());
            }

        // #__isset/__unset

            function test_isset_and_unset() {
                ensure(!isset($this->struct->test));
                $this->struct->test = 'testing';
                ensure(isset($this->struct->test));
                unset($this->struct->test);
                ensure(!isset($this->struct->test));
            }

        // #extend

            function test_extend() {
                ensure(empty($this->struct->ancestors));
                ensure(!isset($this->struct->methods['asset_path']));
                assert_equal($this->struct, $this->struct->extend($this->assets));
                assert_equal(array($this->assets), $this->struct->ancestors);
                assert_equal(array($this->assets), $this->struct->methods['asset_path']);
            }

            function test_extend_with_multiple_classes() {
                $this->struct->extend(array($this->assets, $this->s3));
                assert_equal(array($this->s3, $this->assets), $this->struct->ancestors);
            }

            function test_extend_with_duplicate_classes() {
                $this->struct->extend($this->assets)->extend($this->assets);
                assert_equal(array($this->assets), $this->struct->ancestors);
            }

            function test_extend_should_call_extended_callback() {
                $class = $this->assets;
                assert_null($class::$extended_struct);
                $this->struct->extend($class, 'TESTING');
                assert_equal(array($this->struct, 'TESTING'), $class::$extended_struct);
            }

        // #method

            function test_method() {
                $this->struct->extend(array($this->assets, $this->s3));
                assert_null($this->struct->method('missing'));
                assert_equal(array($this->s3, 'bad_super'), $this->struct->method('bad_super'));
                assert_equal(array($this->s3, 'asset_path'), $this->struct->method('asset_path'));
                assert_equal(array($this->assets, 'asset_path'), $this->struct->method('asset_path', $this->s3));
            }

        // #method_missing

            function test_method_missing() {
                $struct = $this->struct;
                assert_throws('BadMethodCallException', function() use ($struct) { $struct->method_missing('missing'); });
            }

        // #send

            function test_send() {
                $struct = $this->struct->extend($this->assets);
                assert_equal('/assets/test.png', $this->struct->send('asset_path', array('test.png')));
                assert_throws('BadMethodCallException', function() use ($struct) { $struct->send('missing'); });
            }

        // #super

            function test_super() {
                $struct = $this->struct;
                assert_throws('BadMethodCallException', function() use ($struct) { $struct->super(); });
                $struct->extend($this->assets)->extend($this->s3);
                assert_equal('http://example.s3.amazonaws.com/assets/test.png', $struct->asset_path('test.png'));
                assert_throws('BadMethodCallException', function() use ($struct) { $struct->bad_super(); });
            }

        // ::error_handler

            function test_error_handler() {
                $file = dirname(dirname(__DIR__)).DIRECTORY_SEPARATOR.'open_struct.php';
                ensure(!OpenStruct::error_handler(1, 'error message', __FILE__, 1, array()));
                ensure(!OpenStruct::error_handler(1, 'error message', $file, 1, array()));
                ensure(!OpenStruct::error_handler(OpenStruct::NON_STATIC_METHOD_CALL_ERROR, 'error message', __FILE__, 1, array()));
                ensure(!OpenStruct::error_handler(OpenStruct::NON_STATIC_METHOD_CALL_ERROR, 'error message', $file, 1, array()));
                assert_null(OpenStruct::error_handler(OpenStruct::NON_STATIC_METHOD_CALL_ERROR, 'error message', $file.'-eval', 1, array()));
            }

            function test_error_handler_with_previous_error_handler() {
                $original = OpenStruct::$previous_error_handler;
                OpenStruct::$previous_error_handler = array(__CLASS__.'\\ErrorHandler', 'error_handler');
                assert_equal('test', OpenStruct::error_handler(1, 'error message', $file, 1, array()));
                OpenStruct::$previous_error_handler = $original;
            }

        // ::register_error_handler

            function test_register_error_handler() {
                restore_error_handler();
                $get_error_handler = $this->get_error_handler;
                $handler = $get_error_handler();
                assert_equal($handler, OpenStruct::register_error_handler());
                assert_equal(array('OpenStruct', 'error_handler'), $get_error_handler());
                assert_not_equal($handler, $get_error_handler());
            }

    }
}