<?php
namespace DoctrineEncrypt\Encryptors;

class OpenSslEncryptor implements EncryptorInterface {
    private $method;
    private $secretKey;
    private $separator;

    public function __construct($key) {
        $this->method = 'aes-256-cbc';
        $this->secretKey = $key;
        $this->separator = ':';
    }

    private function getIv() {
        return openssl_random_pseudo_bytes(
            openssl_cipher_iv_length($this->method)
        );
    }

    public function encrypt($data) {
        if (empty(trim($data)) === true) {
            return $data;
        }

        $this->isValidKey($data);
        $iv = $this->getIv();
        $cipherText = openssl_encrypt(
            $data,
            $this->method,
            $this->secretKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        return base64_encode(
            base64_encode($iv) . $this->separator. $cipherText
        );

    }

    public function decrypt($data) {
        if (empty(trim($data)) === true) {
            return $data;
        }

        $this->isValidKey($data);
        $decodedData = base64_decode($data);
        list($iv, $cipherText) = array_pad(
            explode($this->separator, $decodedData, 2), 2, null
        );

        $plainText = openssl_decrypt(
            $cipherText,
            $this->method,
            $this->secretKey,
            OPENSSL_RAW_DATA,
            base64_decode($iv)
        );

        return $plainText;
    }

    private function isValidKey() {
        if (mb_strlen($this->secretKey, '8bit') !== 32) {
            throw new \Exception("Needs a 256-bit key!");
        }
    }

}
