<?php

/**
 * File
 *
 * @package   Kirby CMS
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      http://getkirby.com
 * @copyright Bastian Allgeier
 * @license   http://getkirby.com/license
 */
abstract class FileAbstract extends Media {

  static public $methods = array();

  public $kirby;
  public $site;
  public $page;
  public $files;
  public $thumb = [];

  /**
   * Constructor
   *
   * @param Files The parent files collection
   * @param string The filename
   */
  public function __construct(Files $files, $filename) {

    $this->kirby = $files->kirby;
    $this->site  = $files->site;
    $this->page  = $files->page;
    $this->files = $files;
    $this->root  = $this->files->page()->root() . DS . $filename;

    parent::__construct($this->root);

  }

  /**
   * Returns the kirby object
   *
   * @return Kirby
   */
  public function kirby() {
    return $this->kirby;
  }

  /**
   * Returns the parent site object
   *
   * @return Site
   */
  public function site() {
    return $this->site;
  }

  /**
   * Returns the parent page object
   *
   * @return Page
   */
  public function page() {
    return $this->page;
  }

  /**
   * Returns the parent files collection
   *
   * @return Files
   */
  public function files() {
    return $this->files;
  }

  /**
   * Returns the full root for the content file
   *
   * @return string
   */
  public function textfile() {
    return $this->page->textfile($this->filename());
  }

  public function siblings() {
    return $this->files->not($this->filename);
  }

  public function next() {
    $siblings = $this->files;
    $index    = $siblings->indexOf($this);
    if($index === false) return false;
    return $this->files->nth($index+1);
  }

  public function hasNext() {
    return $this->next();
  }

  public function prev() {
    $siblings = $this->files;
    $index    = $siblings->indexOf($this);
    if($index === false) return false;
    return $this->files->nth($index-1);
  }

  public function hasPrev() {
    return $this->prev();
  }

  /**
   * Returns the absolute URL for the file
   *
   * @return string
   */
  public function url($raw = false) {
    if($raw || $this->type() != 'image' || empty($this->thumb)) {
      return $this->page->contentUrl() . '/' . rawurlencode($this->filename);
    } else {
      return $this->kirby->component('thumb')->url($this);      
    }  
  }

  /**
   * Thumb creator
   * 
   * @param array $params
   * @return Media
   */
  public function thumb($params = array()) {

    if($this->type() != 'image') return $this;

    $self = clone $this;

    if($params === false) {
      
      // remove all thumb settings
      $self->thumb = array();
  
      // remove the overwritten dimensions      
      unset($self->cache['dimensions']);

    } else {

      if(is_string($params)) {
        $versions = (array)kirby()->option('thumbs', array());
        $version  = a::get($versions, $params);

        if(empty($version)) return $this;

        $self->thumb = $version;
      } else {
        $self->thumb = array_merge($self->thumb, $params);
      }

      $crop   = a::get($self->thumb, 'crop');
      $width  = a::get($self->thumb, 'width', null);
      $height = a::get($self->thumb, 'height', null);

      if($crop === true && $width) {
        $self->dimensions()->crop($width, $height);
      } else {
        $self->dimensions()->fitWidthAndHeight($width, $height);      
      }

    }

    return $self;

  }

  public function width($width = null) {
    return $width ? $this->thumb(['width' => $width]) : parent::width();
  }

  public function height($height = null) {
    return $height ? $this->thumb(['height' => $height]) : parent::height();
  }

  public function blur($status = true) {
    return $this->thumb(['blur' => $status]);
  }

  public function quality($quality = 90) {
    return $this->thumb(['quality' => $quality]);
  }

  public function grayscale($status = true) {
    return $this->thumb(['grayscale' => $status]);
  }

  public function bw($status = true) {
    return $this->grayscale($status);
  }

  /**
   * Scales the image if possible
   * 
   * @param int $width
   * @param mixed $height
   * @param mixed $quality
   * @return Media
   */
  public function resize($width, $height = null, $quality = null) {

    $params = array('width' => $width);

    if($height)  $params['height']  = $height;
    if($quality) $params['quality'] = $quality;

    return $this->thumb($params);

  }

  /**
   * Scales and crops the image if possible
   * 
   * @param int $width
   * @param mixed $height
   * @param mixed $quality
   * @return Media
   */
  public function crop($width, $height = null, $quality = null) {

    $params = array('width' => $width, 'crop' => true);

    if($height)  $params['height']  = $height;
    if($quality) $params['quality'] = $quality;

    return $this->thumb($params);

  }

  /**
   * Returns the relative URI for the image
   *
   * @return string
   */
  public function uri() {
    return $this->page->uri() . '/' . rawurlencode($this->filename);
  }

  /**
   * Returns the full directory path starting from the content folder
   *
   * @return string
   */
  public function diruri() {
    return $this->page->diruri() . '/' . rawurlencode($this->filename);
  }

  /**
   * Get the meta information
   *
   * @return Content
   */
  public function meta() {

    if(isset($this->cache['meta'])) {
      return $this->cache['meta'];
    } else {

      $inventory = $this->page->inventory();
      $file      = isset($inventory['meta'][$this->filename]) ? $this->page->root() . DS . $inventory['meta'][$this->filename] : null;

      return $this->cache['meta'] = new Content($this->page, $file);

    }

  }

  /**
   * Custom modified method for files
   * 
   * @param string $format
   * @return string
   */
  public function modified($format = null, $handler = null) {
    return parent::modified($format, $handler ? $handler : $this->kirby->options['date.handler']);
  }

  /**
   * Magic getter for all meta fields
   *
   * @return Field
   */
  public function __call($key, $arguments = null) {
    if(isset(static::$methods[$key])) {
      if(!$arguments) $arguments = array();
      array_unshift($arguments, clone $this);
      return call(static::$methods[$key], $arguments);
    } else {
      return $this->meta()->get($key, $arguments);
    }
  }

  /**
   * Generates a new filename for a given name
   * and makes sure to handle badly given extensions correctly
   * 
   * @param string $name
   * @return string
   */
  public function createNewFilename($name, $safeName = true) {

    $name = basename($safeName ? f::safeName($name) : $name);
    $ext  = f::extension($name);

    // remove possible extensions
    if(in_array($ext, f::extensions())) {
      $name = f::name($name);      
    }

    return trim($name . '.' . $this->extension(), '.');

  }

  /**
   * Renames the file and also its meta info txt
   *
   * @param string $filename
   * @param boolean $safeName
   */
  public function rename($name, $safeName = true) {

    $filename = $this->createNewFilename($name, $safeName);
    $root     = $this->dir() . DS . $filename;

    if(empty($name)) {
      throw new Exception('The filename is missing');
    }

    if($root == $this->root()) return $filename;

    if(file_exists($root)) {
      throw new Exception('A file with that name already exists');
    }

    if(!f::move($this->root(), $root)) {
      throw new Exception('The file could not be renamed');
    }

    $meta = $this->textfile();

    if(file_exists($meta)) {
      f::move($meta, $this->page->textfile($filename));
    }

    // reset the page cache
    $this->page->reset();

    // reset the basics
    $this->root     = $root;
    $this->filename = $filename;
    $this->name     = $name;
    $this->cache    = array();

    cache::flush();

    return $filename;

  }

  public function update($data = array()) {

    $data = array_merge((array)$this->meta()->toArray(), $data);

    foreach($data as $k => $v) {
      if(is_null($v)) unset($data[$k]);
    }

    if(!data::write($this->textfile(), $data, 'kd')) {
      throw new Exception('The file data could not be saved');
    }

    // reset the page cache
    $this->page->reset();

    // reset the file cache
    $this->cache = array();

    cache::flush();
    return true;

  }

  public function delete() {

    // delete the meta file
    f::remove($this->textfile());

    if(!f::remove($this->root())) {
      throw new Exception('The file could not be deleted');
    }

    cache::flush();
    return true;

  }

  /**
   * Get formatted date fields
   *
   * @param string $format
   * @param string $field
   * @return mixed
   */
  public function date($format = null, $field = 'date') {
    if($timestamp = strtotime($this->meta()->$field())) {
      if(is_null($format)) {
        return $timestamp;
      } else {
        return $this->kirby->options['date.handler']($format, $timestamp);
      }
    } else {
      return false;
    }
  }

  /**
   * Converts the entire file object into 
   * a plain PHP array
   * 
   * @param closure $callback Filter callback
   * @return array
   */
  public function toArray($callback = null) {

    $data = parent::toArray();

    // add the meta content
    $data['meta'] = $this->meta()->toArray();

    if(is_null($callback)) {
      return $data;
    } else {
      return array_map($callback, $data);
    }

  }

  /**
   * Makes it possible to echo the entire object
   *
   * @return string
   */
  public function __toString() {
    return (string)$this->root;
  }

}
