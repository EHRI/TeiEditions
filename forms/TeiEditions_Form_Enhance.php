<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */

require_once __DIR__ . '/../vendor/autoload.php';

class TeiEditions_Form_Enhance extends Omeka_Form
{
    // TODO: put this in config somewhere???
    private static $LANGS = ["eng", "ces", "deu", "nld", "fra", "rus", "pol",];

    /**
     * @throws Zend_Form_Exception
     */
    public function init()
    {
        parent::init();

        $this->setAttrib('id', 'tei-editions-enhance-form');

        $iso = new Matriphe\ISO639\ISO639;

        $this->addElement('file', 'file', [
            'required' => true,
            'label' => __('Select input TEI XML file'),
            'description' => __('Select the TEI file to be modified.'),
        ]);

        $this->addElement('file', 'dict', [
            'required' => false,
            'label' => __('Dictionary TEI file'),
            'description' => __('A TEI local dictionary file (optional).'),
        ]);

        $this->addElement('select', 'lang', [
            'id' => 'tei-editions-enhance-lang',
            'label' => __('Language'),
            'class' => 'text',
            'multiOptions' => array_reduce($this::$LANGS, function($res, $code) use ($iso) {
                $res[$code] = $iso->languageByCode2t($code);
                return $res;
            }, []),
            'description' => __('The preferred language for fetched entity data.')
        ]);

        $this->addElement('submit', 'submit', [
            'label' => __('Download Transformed TEI'),
            'id' => 'tei-editions-submit'
        ]);

        $this->addDisplayGroup(['file', 'dict', 'lang'], 'tei-editions_info');
        $this->addDisplayGroup(['submit'], 'tei-editions_submit');
    }
}