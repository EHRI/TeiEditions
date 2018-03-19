<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:tei="http://www.tei-c.org/ns/1.0">
    <xsl:output indent="yes" omit-xml-declaration="yes" encoding="utf-8" method="xml"/>
    <xsl:strip-space elements="*"/>

    <xsl:template match="tei:place" name="place-entity">
        <div class="tei-entity-data tei-place">
            <xsl:attribute name="data-ref">
                <xsl:value-of select="./tei:linkGrp/tei:link[@type='normal']/@target"/>
            </xsl:attribute>
            <h3><xsl:value-of select="./tei:placeName"/></h3>
            <xsl:if test="./tei:location/tei:geo">
                <div class="tei-entity-data-geo">
                    <xsl:value-of select="./tei:location/tei:geo"/>
                </div>
            </xsl:if>
        </div>
    </xsl:template>

    <xsl:template match="tei:person" name="person-entity">
        <div class="tei-entity-data tei-person">
            <xsl:attribute name="data-ref">
                <xsl:value-of select="./tei:linkGrp/tei:link[@type='normal']/@target"/>
            </xsl:attribute>
            <h3><xsl:value-of select="./tei:persName"/></h3>
        </div>
    </xsl:template>

    <xsl:template match="tei:org" name="org-entity">
        <div class="tei-entity-data tei-org">
            <xsl:attribute name="data-ref">
                <xsl:value-of select="./tei:linkGrp/tei:link[@type='normal']/@target"/>
            </xsl:attribute>
            <h3><xsl:value-of select="./tei:orgName"/></h3>
        </div>
    </xsl:template>

    <xsl:template match="tei:item" name="term-entity">
        <div class="tei-entity-data tei-item">
            <xsl:attribute name="data-ref">
                <xsl:value-of select="./tei:linkGrp/tei:link[@type='normal']/@target"/>
            </xsl:attribute>
            <h3><xsl:value-of select="./tei:name"/></h3>
        </div>
    </xsl:template>


    <xsl:template match="tei:p" name="identity">
        <xsl:apply-templates select="node()|@*"/>
    </xsl:template>

    <xsl:template match="tei:term|tei:placeName|tei:persName|tei:orgName">
        <span class="tei-entity">
            <xsl:attribute name="data-ref"><xsl:value-of select="attribute::ref"/></xsl:attribute>
            <xsl:apply-templates/>
        </span>
    </xsl:template>

    <xsl:template match="/">
        <xsl:for-each select="/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:listPlace">
            <xsl:apply-templates/>
        </xsl:for-each>

        <xsl:for-each select="/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:list">
            <xsl:apply-templates/>
        </xsl:for-each>

        <xsl:for-each select="/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:listPerson">
            <xsl:apply-templates/>
        </xsl:for-each>

        <xsl:for-each select="/tei:TEI/tei:teiHeader/tei:fileDesc/tei:sourceDesc/tei:listOrg">
            <xsl:apply-templates/>
        </xsl:for-each>



        <div class="tei-text">
            <xsl:for-each select="/tei:TEI/tei:text/tei:body/tei:p">
                <p><xsl:apply-templates/></p>
            </xsl:for-each>
        </div>
    </xsl:template>
</xsl:stylesheet>