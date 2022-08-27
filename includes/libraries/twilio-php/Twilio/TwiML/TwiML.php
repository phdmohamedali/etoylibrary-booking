<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */

namespace Twilio\TwiML;

/**
 * @property $name string XML element name
 * @property $attributes array XML attributes
 * @property $value string XML body
 * @property $children TwiML[] nested TwiML elements
 */
abstract class TwiML {
    private $name;
    private $attributes;
    private $value;
    private $children;

    /**
     * TwiML constructor.
     * 
     * @param string $name XML element name
     * @param string $value XML value
     * @param array $attributes XML attributes
     */
    public function __construct($name, $value = null, $attributes = array()) {
        $this->name = $name;
        $this->value = $value;
        $this->attributes = $attributes;
        $this->children = array();
    }

    /**
     * Add a TwiML element.
     * 
     * @param TwiML $twiml TwiML element to add
     * @return TwiML $this
     */
    public function append($twiml) {
        $this->children[] = $twiml;
        return $this;
    }

    /**
     * Add a TwiML element.
     * 
     * @param TwiML $twiml TwiML element to add
     * @return TwiML added TwiML element
     */
    public function nest($twiml) {
        $this->children[] = $twiml;
        return $twiml;
    }

    /**
     * Set TwiML attribute.
     * 
     * @param string $key name of attribute
     * @param string $value value of attribute
     * @return TwiML $this
     */
    public function setAttribute($key, $value) {
        return $this->attributes[$key] = $value;
    }

    /**
     * Convert TwiML to XML string.
     * 
     * @return string TwiML XML representation
     */
    public function asXML() {
        return $this->__toString();
    }

    /**
     * Convert TwiML to XML string.
     * 
     * @return string TwiML XML representation
     */
    public function __toString() {
        return str_replace(
            '<?xml version="1.0"?>',
            '<?xml version="1.0" encoding="UTF-8"?>',
            $this->xml()->asXML()
        );
    }

    /**
     * Build TwiML children.
     * 
     * @param TwiML[] $children Children to build
     * @param \SimpleXMLElement $element Base XML element
     */
    private function buildChildren($children, $element) {
        foreach ($children as $child) {
            $childElement = $element->addChild($child->name);
            self::buildElement($child, $childElement);
            self::buildChildren($child->children, $childElement);
        }
    }

    /**
     * Build TwiML element.
     * 
     * @param TwiML $twiml TwiML element to build
     * @param \SimpleXMLElement $element Base XML element
     */
    private function buildElement($twiml, $element) {
        if (is_string($twiml->value)) {
            $element[0] = $twiml->value;
        }

        foreach ($twiml->attributes as $name => $value) {
            if (is_bool($value)) {
                $value = ($value === true) ? 'true' : 'false';
            }
            $element->addAttribute($name, $value);
        }
    }

    /**
     * Build XML element.
     * 
     * @return \SimpleXMLElement Build TwiML element
     */
    private function xml() {
        $element = new \SimpleXMLElement('<' . $this->name . '/>');
        self::buildElement($this, $element);
        self::buildChildren($this->children, $element);
        return $element;
    }
}