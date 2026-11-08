<?php

namespace App\Interfaces;

interface ProdiInterface
{
    /**
     * Get All Data
     *
     * @return array All Data
     */
    public function getAll();

    /**
     * Get Item Details By ID
     *
     * @param int $id
     * @return object Get Product
     */
    public function getByID(int $id);
}