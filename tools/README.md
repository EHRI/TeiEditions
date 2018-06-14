TEI Entity Lookup
--------------------

The script `./tools/enhance-tei.php` is a command-line tool that looks for external entity references
in the body of a TEI text, attempts to fetch information about those entities from supported web sources (currently:
[Geonames](http://geonames.org) and the [EHRI Portal](https://portal.ehri-project.eu)), and outputs a new TEI with
that information embedded in the TEI header.

For example, the minimal TEI document:

```xml
<TEI xmlns="http://www.tei-c.org/ns/1.0" xml:id="testing">
    <teiHeader>
        <fileDesc>
            <!-- SNIP -->
            <sourceDesc>
                <bibl>King's College London</bibl>
            </sourceDesc>
        </fileDesc>
    </teiHeader>
    <text>
        <body>
            <p>An example placename: <placeName ref="http://www.geonames.org/2643743/">London</placeName>.</p>
        </body>
    </text>
</TEI>
```

 would become:

```xml
<TEI xmlns="http://www.tei-c.org/ns/1.0" xml:id="testing">
    <teiHeader>
        <fileDesc>
            <!-- SNIP -->
            <sourceDesc>
                <bibl>King's College London</bibl>
                <listPlace>
                    <place>
                      <placeName>London</placeName>
                      <location>
                        <geo>51.50853 -0.12574</geo>
                      </location>
                      <linkGrp>
                        <link type="normal" target="http://www.geonames.org/2643743/"/>
                        <link type="desc" target="http://en.wikipedia.org/wiki/London"/>
                      </linkGrp>
                    </place>
                </listPlace>
            </sourceDesc>
        </fileDesc>
    </teiHeader>
    <text>
        <body>
            <p>An example placename: <placeName ref="http://www.geonames.org/2643743/">London</placeName>.</p>
        </body>
    </text>
</TEI>
```

Supported entities are `<placeName`, `<persName>`, `orgName`, and `<term>`. If an entity reference does not have a `ref=` attribute 
an entity will be added to the header without external information using just the enclosed text.

For entities for which an external source either does not exist or is not supported, local "dictionary" file(s) can be supplied
with the `--dict <file.xml>` option. A dictionary file consists of a TEI document containing entities in the `<sourceDesc>` that
can be referred to by other files, using an anchor reference to their `xml:id` attribute.

For example, a dictionary file might resemble:

```xml
<TEI xmlns="http://www.tei-c.org/ns/1.0" xml:id="EHRI-BF-local_dictionary">
    <teiHeader>
        <fileDesc>
            <sourceDesc>
                <listPlace>
                    <place xml:id="test-place">
                        <placeName>Test Place</placeName>
                        <location>
                            <geo>51.848637 -0.55462</geo>
                        </location>
                        <note><p>Testing.</p></note>
                        <linkGrp>
                            <link type="desc" target="https://en.wikipedia.org/wiki/Whipsnade_Zoo"/>
                        </linkGrp>
                    </place>
                </listPlace>
            </sourceDesc>
        </fileDesc>
    </teiHeader>
    <text>
        <body>
        </body>
    </text>
</TEI>
```

 and a reference to it could be made in another file using the `<placeName ref="#test-place">The Place</placeName>`. This will result
 in the data from the dictionary file being copied to the subject TEI.
 
 Usage
 -----
 
     ./tools/enhance-tei.xml [-d|--dict <dict.xml>] <source-tei,xml>
