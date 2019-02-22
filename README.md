# doctrine-encrypt

Package encrypts and decrypts Doctrine fields through life cycle events.

## Installation
Add `aviwar/doctrine-encrypt` to your Composer manifest by following methods.

```js
{
    "require": {
        "aviwar/doctrine-encrypt": "~1.0"
    }
}
```

## Configuration
Add the event subscriber to your entity manager's event manager. Assuming `$em`
is your configured entity manager:

```php
<?php

$subscriber = new DoctrineEncryptSubscriber(
    new \Doctrine\Common\Annotations\AnnotationReader,
    new \DoctrineEncrypt\Encryptors\OpenSslEncryptor($secretKey)
);

AnnotationRegistry::registerLoader('class_exists');
$eventManager = $em->getEventManager();
$eventManager->addEventSubscriber($subscriber);
```
<b>Note:</b> For 256-bit `$secretKey` use [RandomKeyGen]

## Usage
```php
<?php
namespace Your\CoolNamespace;

use Doctrine\ORM\Mapping\{Id, Column, GeneratedValue};
use DoctrineEncrypt\Configuration\Encrypted;

/**
 * @Entity
 */
class Entity
{
    /**
     * @Id
     * @GeneratedValue(strategy="AUTO")
     * @Column(type="integer")
     */
    protected $id;

    /**
     * @Column(type="text")
     * @Encrypted
     */
    protected $secret_data;
}
```

## License

This bundle is under the MIT license. See the complete license in the bundle

## Versions

I'm using Semantic Versioning like described [here](http://semver.org).

[RandomKeyGen]: https://randomkeygen.com/
