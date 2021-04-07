<?php
/**
 * @package TeiEditions
 *
 * @copyright Copyright 2021 King's College London Department of Digital Humanities
 */


class TeiEditions_IngestForm extends Omeka_Form
{
    // TODO: put this in config somewhere???
    private static $LANGS = ["eng", "ces", "deu", "nld", "fra", "rus", "pol",];

    /**
     * @throws Zend_Form_Exception
     */
    public function init()
    {
        parent::init();

        $iso = new Matriphe\ISO639\ISO639;

        $this->addElement('file', 'file', [
            'required' => true,
            'label' => __('Select TEI XML or zip archive'),
            'description' => __('Select the file to be ingested. Multiple files can be ingested if contained within a zip archive.'),
        ]);

        $this->addElement('checkbox', 'create_exhibit', [
            'id' => 'tei-editions-upload-create-exhibit',
            'label' => __('Create Neatline Exhibit'),
            'class' => 'checkbox',
            'description' => __('Create a Neatline Exhibit containing records for each place element contained in the TEI')
        ]);

        $this->addElement('checkbox', 'enhance', [
            'id' => 'tei-editions-enhance',
            'label' => __('Enhance TEI'),
            'class' => 'checkbox',
            'required' => false,
            'description' => __('Attempt to enhance the TEI by populating the TEI header with canonical references to 
    entities marked in the text by &lt;term&gt;, &lt;persName&gt;, &lt;orgName&gt; and &lt;place&gt; markup
    if they contain \'ref\' attributes that point to the Geonames or EHRI data sources.')
        ]);

        $this->addElement('file', 'enhance_dict', [
            'id' => 'tei-editions-enhance-dict',
            'required' => false,
            'label' => __('Dictionary TEI file'),
            'description' => __('A TEI local dictionary file (optional).'),
        ]);

        $this->addElement('select', 'enhance_lang', [
            'id' => 'tei-editions-enhance-lang',
            'label' => __('Language'),
            'class' => 'text',
            'required' => false,
            'multiOptions' => array_reduce($this::$LANGS, function($res, $code) use ($iso) {
                $res[$code] = $iso->languageByCode2t($code);
                return $res;
            }, []),
            'description' => __('The preferred language for fetched entity data.')
        ]);

        $this->addElement('submit', 'submit', [
            'label' => __('Upload File')
        ]);

        $this->addDisplayGroup(['file', 'create_exhibit', 'enhance', 'enhance_dict', 'enhance_lang'], 'tei-editions_info');
        $this->addDisplayGroup(['submit'], 'tei-editions_submit');
    }
}