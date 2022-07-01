# SilverStripe Multi Step Forml module

[![Build Status](https://travis-ci.com/lekoala/silverstripe-multi-step-form.svg?branch=master)](https://travis-ci.com/lekoala/silverstripe-multi-step-form/)
[![scrutinizer](https://scrutinizer-ci.com/g/lekoala/silverstripe-multi-step-form/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/lekoala/silverstripe-multi-step-form/)
[![Code coverage](https://codecov.io/gh/lekoala/silverstripe-multi-step-form/branch/master/graph/badge.svg)](https://codecov.io/gh/lekoala/silverstripe-multi-step-form)

## Intro

A simple alternative to [multiform](https://github.com/silverstripe/silverstripe-multiform)

This module does not require storage in the back end and provide a somewhat easier DX.

## How it works

Each step of your form should be named the same.

-   MyFormStep1
-   MyFormStep2
-   ...

They should all extends the base `MultiStepForm` class and implements the following abstract methods:

-   buildFields : returns a field list
-   getStepTitle : get the step title

```php
class MyFormStep1 extends MultiStepForm
{
    public static function getStepTitle()
    {
        return 'My Step';
    }

    public function buildFields()
    {
        $fields = new FieldList();
        return $fields;
    }
}
```

In your controller, you declare a form like this:

```php
private static $allowed_actions = array(
    'MyForm'
);

public function MyForm()
{
    $class = MyFormStep1::classForCurrentStep($this);
    return new $class($this);
}
```

## Template helpers

You can display the steps using

```
$MyForm.DisplaySteps
```

This relies on some default styles that are added by default. You can disable styles and edit custom classes with:

```yml
LeKoala\MultiStepForm\MultiStepForm:
    include_css: true
    class_active: "current bg-primary text-white"
    class_inactive: "link"
    class_completed: "msf-completed bg-primary text-white"
    class_not_completed: "msf-not-completed bg-light text-muted"
```

## TODO

-   Doc
-   Tests

## Compatibility

Tested with 4.6 but should work on any ^4.4 projects

## Maintainer

LeKoala - thomas@lekoala.be
