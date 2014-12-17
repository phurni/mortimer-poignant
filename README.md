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

 * UserStamping
   Provides automatic filling of `create_by_id`, `updated_by_id` and `deleted_by_id` attributes.

### UserStamping

Provides automatic filling of `create_by_id`, `updated_by_id` and `deleted_by_id` attributes.
These are filled only if the columns exists on the table, so no need to worry about using or not the trait,
simply use and forget it.

This trait stamps the user by using its `id` not a name string. So you may add relations on your models to
associate them correctly.

Every column name may be customized by defining them in your model:

```php
use Mortimer\Poignant\UserStamping;

class MyModel extends Eloquent {
  use UserStamping;
  
  protected static $CREATED_BY = 'FK_created_by';
}
```

You may also override the value stored in those columns:

```php
class MyModel extends Eloquent {
  use UserStamping;
  
  public function getUserStampValue()
  {
      // This is the default value, return what you desire here to override the default behaviour
      return \Auth::user()->getKey();
  }
}
```

