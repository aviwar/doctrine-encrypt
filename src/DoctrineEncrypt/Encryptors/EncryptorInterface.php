<?php
namespace DoctrineEncrypt\Encryptors;

interface EncryptorInterface {
    public function encrypt($data);
    public function decrypt($data);
}
