# OpenStruct

An `OpenStruct` is a data structure that allows the definition of arbitrary methods and properties **at runtime**.

## Installation

If you're using `composer` simply add the `einstein/open-struct` dependency to your `composer.json` file.

Otherwise you can manually install it by cloning the repository somewhere in your php `include_path`.

    git clone git@github.com:einstein/open_struct.git`
    require 'open_struct/open_struct.php';

## Usage

    $person = new OpenStruct;
    $person->name = 'John';
    $person->age = 35;

    echo $person->name;     # => 'John'
    echo $person->age;      # => 35
    echo $person->address;  # => null

`OpenStruct` uses an `array` internally to store properties and can even be initialized with one:

    $person = new OpenStruct(array('name' => 'John', 'age' => 35));

    echo $person->name;     # => 'John'
    echo $person->age;      # => 35
    echo $person->address;  # => null

    print_r($person->properties);  # => array('name' => 'John', 'age' => 35)

`OpenStruct` objects can `extend` classes allowing new methods to be defined at runtime (`$this` even resolves correctly):

    class AssetHelpers {
        function asset_path($path) {
            return '/assets/'.$path;
        }
    }

    $helpers = new OpenStruct;
    $helpers->extend('AssetHelpers');

    echo $helpers->asset_path('cat.png');  # => '/assets/cat.png'
    echo $helpers->asset_path('dog.png');  # => '/assets/dog.png'

Methods can even be overridden (notice the call to `$this->super()` which references the example above):

    class S3AssetHelpers {
        function asset_path($path) {
            return 'http://'.$this->s3_bucket.'.s3.amazonaws.com'.$this->super($path);
        }
    }

    $helpers->extend('S3AssetHelpers');
    $helpers->s3_bucket = 'example';

    echo $helpers->asset_path('cat.png');  # => 'http://example.s3.amazonaws.com/assets/cat.png'
    echo $helpers->asset_path('dog.png');  # => 'http://example.s3.amazonaws.com/assets/dog.png'

    print_r($helpers->ancestors);  # => array('S3AssetHelpers', 'AssetHelpers')

Note that `super` can only be called from within a method:

    $struct = new OpenStruct;
    $struct->super();  # => BadMethodCallException - Undefined method OpenStruct::super()

A wildcard/catch-all method `method_missing` can be defined as well (equivalent of PHP's `__call`)

    $person = new OpenStruct(array('name' => 'Bob'));

    $person->get_name();  # => BadMethodCallException - Undefined method OpenStruct::get_name()

    class Getters {
        function method_missing($method, $arguments) {
            if (preg_match('/^get_(.+)$/', $method, $matches) && isset($this->{$matches[1]})) {
                return $this->{$matches[1]};
            } else {
                return $this->super($method, $arguments);
            }
        }
    }

    $person->extend('Getters');

    $person->get_name();   # => 'Bob'
    $person->get_age();    # => BadMethodCallException - Undefined method OpenStruct::get_age()
    $person->undefined();  # => BadMethodCallException - Undefined method OpenStruct::undefined()

If you `extend` a class that has a static method called `extended`, it will be called and
passed the current `OpenStruct` instance and any additional parameters passed to `extend` as arguments.

You can use it like a constructor/initializer:

    class S3AssetHelpers {
        static function extended($struct, $bucket = 'example') {
            $struct->s3_bucket = $bucket;
        }

        function asset_path($path) {
            return 'http://'.$this->s3_bucket.'.s3.amazonaws.com/'.$path;
        }
    }

    $helpers = new OpenStruct;
    $helpers->extend('S3AssetHelpers', 'test');

    echo $helpers->asset_path('cat.png');  # => 'http://test.s3.amazonaws.com/cat.png'
    echo $helpers->asset_path('dog.png');  # => 'http://test.s3.amazonaws.com/dog.png'

The `extend` method also accepts an `array` of classes to extend as the first argument. These method calls all do the same thing:

    $struct = new OpenStruct;

    $struct->extend(array('AssetHelpers', 'S3AssetHelpers'));

    $struct->extend('AssetHelpers');
    $struct->extend('S3AssetHelpers');

    $struct->extend('AssetHelpers')->extend('S3AssetHelpers');

PHP has many [reserved keywords](http://php.net/manual/en/reserved.keywords.php) that throw fatal errors
when you try to define them as methods e.g. `function include() { }`. `OpenStruct` allows you to define methods
with keyword names by prefixing them with `__` e.g. `function __include() { }`. This allows you to call
`$struct->include()` without throwing fatal errors.

`OpenStruct` also includes an `eval` method which accepts a `$file` and optional array of `$locals`. It will
[extract](http://php.net/extract) any locals and [include](http://www.php.net/manual/en/function.include.php)
`$file` within the context of the `OpenStruct` instance. It also accepts an optional third argument `$extract_properties`
which will also extract references to all of the `OpenStruct` instance's properties before including `$file`.
`$extract_properties` defaults to `false`.

## Testing

`OpenStruct` tests require [jaz303/ztest](http://github.com/jaz303/ztest)

Simply download it to `open_struct/test/ztest` (or anywhere else in your PHP `include_path`), then run `test/run`
