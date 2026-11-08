<?php

namespace App\Interfaces;

interface MahasiswaInterface
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

    /**
     * Create New Item
     *
     * @param array $data
     * @return object Created Product
     */
    public function create(array $data);
}