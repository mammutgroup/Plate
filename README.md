Iran Vehicle Plate Number Package
===================

### Requirements:
- php ~5.4.* 

Instalation:
==========
First add package name to your composer requirements
```json
"require": {
    "plate/plate": "dev"
}
```

Next, update Composer from the Terminal:
>composer update

Next, add your new provider to the providers array of config/app.php:

```php
'providers' => [
    // ...
    Plate\PlateServiceProvider::class,
    // ...
  ]
```

Next, add class alias to the aliases array of config/app.php:

```php
'aliases' => [
   // ...
      'Plate' => Plate\PlateFacade::class
    // ...
]
```

Finally, run:
> php artisan vendor:publish

Ho to use:
====
```php
  $plate = new Plate\Plate();
  $plak = 21 .
      ' ب ' .
      488 .
      ' - ' .
      88 .
      ' ایران';

  $r = $plate->setPlate($plak);
  print_r($r->getparsedData()); exit;
  print_r($plate->isCab());
```

### Get plate as image
```php
  $plate->getImage('path/to/export/image.png');
```

### Add date to image
```php
  $plate->withDate('95-05-01')->getImage('path/to/export/image.png');
```