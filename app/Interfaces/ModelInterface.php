<?php

namespace App\Interfaces;

interface ModelInterface
{
    /**
     * Get All Data
     *
     * @return array All Data
     */
    public function getAll();


    /**
     * Create New Item
     *
     * @param array $data
     * @return object Created Product
     */
    public function create(array $data);

    /**
     * Delete Item By Id
     *
     * @param int $id
     * @return object Deleted Product
     */
    public function delete($id);

    /**
     * Get Item Details By ID
     *
     * @param int $id
     * @return object Get Product
     */
    public function getByID($id);

    /**
     * Update Product By Id and Data
     *
     * @param int $id
     * @param array $data
     * @return object Updated Product Information
     */
    public function update($id, array $data);
}