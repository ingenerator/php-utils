<?php

namespace Ingenerator\PHPUtils\Database;

use Doctrine\ORM\EntityManagerInterface;

class Doctrine2TransactionWrapper implements TransactionWrapper
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function run(callable $func)
    {
        return $this->em->transactional($func);
    }

}
