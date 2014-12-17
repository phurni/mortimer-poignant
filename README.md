# Poignant

Eloquent on steroids, implements RoR like ActiveRecord features as traits, including in-model validations,
user stamping, declarative relations, cascaded operations on relations (save, delete).

Copyright (C) 2014 Pascal Hurni <[https://github.com/phurni](https://github.com/phurni)>

Licensed under the [MIT License](http://opensource.org/licenses/MIT).

## Installation

Add `mortimer/poignant` as a requirement to `composer.json`:

```javascript
{
    "require": {
        "mortimer/poignant": "dev-master"
    }
}
```

Update your packages with `composer update` or install with `composer install`.

The master branch works for both Laravel 4.2 and 5.0

## Getting Started

`Poignant` is a collection of PHP traits for Eloquent models. You are free to use any or many of those traits by simply
adding the `use` statement in your model, like this:

```php
use Mortimer\Poignant\UserStamping;

class MyModel extends Eloquent {
  use UserStamping;
}
```

You may also import all of them by inheriting from the `Model` class:

```php
use Mortimer\Poignant\Model;

class MyModel extends Model {
}
```

## Documentation

Here is the list of traits with their behaviours, you'll find the detailed documentation in the next chapters.
