<?php

namespace App\Interfaces;

interface UsersInterface
{
    /**
     * Get Item Details By ID
     *
     * @param int $id
     * @return object Get Product
     */
    public function getByID(int $id);
}