TeiEditions
===========

An Omeka plugin containing various helpers for managing items derived from TEI.

The plugin allows you to:

 - create Omeka items from uploaded TEI files, with Omeka metadata elements populated via customisable XPath mappings
 - associate images and other tertiary files
 - create Neatline exhibits from location data in the TEI headers

It also adds various view helpers for rendering TEI-derived info and a few Neatline
shortcodes for use within SimplePages and ExhibitBuilder text blocks.

TEI Header Enrichment
---------------------

This plugin contains a command-line tool for looking up entity references in TEI body text and 
adding enriched canonical entity data to the header `<sourceDesc>` section. See the tools 
[README](tools/README.md) file for details.


Structuring a TEI Edition
-------------------------

An edition consists of a set of master TEI XML documents and associated files which might consist of:

 - images
 - PDFs
 - extra TEIs containing translations etc
 
### File Naming Conventions

The plugin relies on file naming conventions to map uploaded TEIs and associated files to the Dublin Core
__identifier__ field of Omeka items. The master TEI XML file and associated TEIs are named as follows:

    [dc-identifier]_[langcode].xml
    
For example, for an item with the identifer `abc-123-def-456` in *English* the TEI would be named:

    abc-123-def-456_EN.xml
    
Note: an underscore must separate the ISO-639-1 language code from the identifier.
    
Associated images, PDFs etc must be named using an ascending index number instead of the language code,
for example:

    abc-123-def-456_01.jpg
    
### TEI XML Structure

When uploading master TEI documents the plugin will extract information from the TEI header and use it to 
populate Omeka metadata fields. These XML-Omeka-element mappings are configurable but the defaults are as
follows:

<dl>
<dt>Identifier</dt>
<dd>`/tei:TEI/tei:teiHeader/tei:profileDesc/tei:creation/tei:idno`</dd>

<dt>Title</dt> 
<dd>`/tei:TEI/tei:teiHeader/tei:fileDesc/tei:titleStmt/tei:title`<dd>

<dt>Subject</dt> 
<dd>`/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:list/tei:item/tei:name`<dd>

<dt>Description</dt> 
<dd>`/tei:TEI/tei:teiHeader/tei:profileDesc/tei:abstract`<dd>

<dt>Creator</dt>
<dd>
   `/tei:TEI/tei:teiHeader/tei:profileDesc/tei:creation/tei:persName`,
   `/tei:TEI/tei:teiHeader/tei:profileDesc/tei:creation/tei:orgName`
</dd>

<dt>Source</dt>
<dd>
   `/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:bibl`, 
   `/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:msDesc/tei:msIdentifier/tei:collection/@ref`
</dd>

<dt>Publisher</dt> 
<dd>`/tei:TEI/tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:publisher/tei:ref`<dd>

<dt>Date</dt> 
<dd>`/tei:TEI/tei:teiHeader/tei:profileDesc/tei:creation/tei:date/@when`<dd>

<dt>Rights</dt> 
<dd>`/tei:TEI/tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:availability/tei:licence`<dd>

<dt>Format</dt> 
<dd>`/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:msDesc/tei:physDesc`<dd>

<dt>Language</dt>
<dd>
    `/tei:TEI/tei:teiHeader/tei:profileDesc/tei:langUsage/tei:language`,
    `/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:bibl/tei:textLang`   
</dd>

<dt>Coverage</dt> 
<dd>`/tei:TEI/tei:teiHeader/tei:profileDesc/tei:creation/tei:placeName<dd>`
</dl>


In addition to the DC fields, the plugin will also map the TEI body text to the **Text** item type
metadata `Text` element and create a new item type **TEI** with elements `Person`, `Organisation`, and `Place`
to which the `tei:sourceDesc/tei:listPerson/tei:person/tei:persName`, 
`tei:sourceDesc/tei:listOrg/tei:org/tei:orgName`, and 
`tei:sourceDesc/tei:listPlace/tei:place/tei:placeName` respectively will be mapped.

### Uploading the master TEI documents

Once TEI files have been created and named correctly they can be ingested into Omeka. Doing so will create
one new Omeka item per master TEI file with metadata populated as per the above XPATH mappings. 

Documents can either be ingested one-by-one or as a zip file containing multiple files.

### Uploading associated files

Once Omeka items have been created from the master TEI documents it is possible to upload any associated
files, which will be assigned to Omeka items according to the naming convention described above. As with
master TEI documents, multiple associated files can be uploaded in a zip.

**Note:** uploading associated files will error if files exist within the uploaded archive that cannot be
paired with an existing Omeka item.

#### Ingest Gotchas

If ingesting a large number of files at once via a zip archive it is easy to exceed the default limits on
PHP's `post_max_size` and `upload_max_filesize` settings. Check your `php.ini` and increase the limits if 
you find this to be the case.  

TODO: more on ingest, screenshots, etc.