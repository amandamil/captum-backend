<?php


namespace ExperienceBundle\Model;


interface FileInterface
{
    /**
     * Returns the file.
     *
     *     public function getFile()
     *     {
     *         return this->file
     *     }
     *
     * @return mixed return file
     */
    public function getFile();

    /**
     * sets new file.
     *
     * @param  mixed file
     *
     *     public function setFile($file)
     *     {
     *         $this->file = $file
     *         return $this
     *     }
     *
     * @return mixed return entity
     */
    public function setFile($file);
}
