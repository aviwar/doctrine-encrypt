<?php
namespace DoctrineEncrypt\Subscribers;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\{LifecycleEventArgs, OnFlushEventArgs, PostFlushEventArgs};
use Doctrine\ORM\{Events, EntityManager};
use DoctrineEncrypt\Encryptors\EncryptorInterface;

class DoctrineEncryptSubscriber implements EventSubscriber {
    const ENCRYPTOR_INTERFACE_NS = 'DoctrineEncrypt\Encryptors\EncryptorInterface';
    const ENCRYPTED_ANN_NAME = 'DoctrineEncrypt\Configuration\Encrypted';

    private $encryptor;
    private $annotationReader;
    private $decodedRegistry = [];
    private $encryptedFieldCache = [];
    private $postFlushDecryptQueue = [];

    public function __construct(
        Reader $annotationReader, EncryptorInterface $encryptor
    ) {
        $this->annotationReader = $annotationReader;
        $this->encryptor = $encryptor;
    }

    public function onFlush(OnFlushEventArgs $args) {
        $em = $args->getEntityManager();
        $unitOfWork = $em->getUnitOfWork();
        $this->postFlushDecryptQueue = [];
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $this->entityOnFlush($entity, $em);
            $unitOfWork->recomputeSingleEntityChangeSet(
                $em->getClassMetadata(get_class($entity)), $entity
            );
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $this->entityOnFlush($entity, $em);
            $unitOfWork->recomputeSingleEntityChangeSet(
                $em->getClassMetadata(get_class($entity)), $entity
            );
        }
    }

    private function entityOnFlush($entity, EntityManager $em) {
        $objId = md5(serialize($entity));
        $fields = [];
        foreach ($this->getEncryptedFields($entity, $em) as $field) {
            $fields[$field->getName()] = array(
                'field' => $field,
                'value' => $field->getValue($entity),
            );
        }

        $this->postFlushDecryptQueue[$objId] = array(
            'entity' => $entity,
            'fields' => $fields,
        );

        $this->processFields($entity, $em);
    }

    public function postFlush(PostFlushEventArgs $args) {
        $unitOfWork = $args->getEntityManager()->getUnitOfWork();
        foreach ($this->postFlushDecryptQueue as $pair) {
            $fieldPairs = $pair['fields'];
            $entity = $pair['entity'];
            $oid = md5(serialize($entity));
            foreach ($fieldPairs as $fieldPair) {
                $field = $fieldPair['field'];
                $field->setValue($entity, $fieldPair['value']);
                $unitOfWork->setOriginalEntityProperty(
                    $oid, $field->getName(), $fieldPair['value']
                );
            }

            $this->addToDecodedRegistry($entity);
        }

        $this->postFlushDecryptQueue = [];
    }

    public function postLoad(LifecycleEventArgs $args) {
        $entity = $args->getEntity();
        $em = $args->getEntityManager();
        if (!$this->hasInDecodedRegistry($entity)) {
            if ($this->processFields($entity, $em, false)) {
                $this->addToDecodedRegistry($entity);
            }
        }
    }

    public function getSubscribedEvents() {
        return array(
            Events::postLoad,
            Events::onFlush,
            Events::postFlush,
        );
    }

    public static function capitalize($word) {
        if (is_array($word)) {
            $word = $word[0];
        }

        return str_replace(
            ' ', '', ucwords(str_replace(array('-', '_'), ' ', $word))
        );
    }

    private function processFields(
        $entity, EntityManager $em, $isEncryptOperation = true
    ) {
        $properties = $this->getEncryptedFields($entity, $em);
        $unitOfWork = $em->getUnitOfWork();
        $oid = md5(serialize($entity));
        foreach ($properties as $refProperty) {
            $value = $refProperty->getValue($entity);
            $value = $isEncryptOperation ?
                $this->encryptor->encrypt($value) :
                $this->encryptor->decrypt($value);

            $refProperty->setValue($entity, $value);
            if (!$isEncryptOperation) {
                $unitOfWork->setOriginalEntityProperty(
                    $oid, $refProperty->getName(), $value
                );
            }
        }

        return !empty($properties);
    }

    private function hasInDecodedRegistry($entity) {
        return isset($this->decodedRegistry[md5(serialize($entity))]);
    }

    private function addToDecodedRegistry($entity) {
        $this->decodedRegistry[md5(serialize($entity))] = true;
    }

    private function getEncryptedFields($entity, EntityManager $em) {
        $className = get_class($entity);
        if (isset($this->encryptedFieldCache[$className])) {
            return $this->encryptedFieldCache[$className];
        }

        $meta = $em->getClassMetadata($className);
        $encryptedFields = [];
        foreach ($meta->getReflectionProperties() as $refProperty) {
            if ($this->annotationReader->getPropertyAnnotation(
                $refProperty, self::ENCRYPTED_ANN_NAME
            )) {
                $refProperty->setAccessible(true);
                $encryptedFields[] = $refProperty;
            }
        }

        $this->encryptedFieldCache[$className] = $encryptedFields;

        return $encryptedFields;
    }
}
