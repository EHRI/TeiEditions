<xsl:stylesheet version="1.0"
                xmlns:xhtml="http://www.w3.org/1999/xhtml"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:tei="http://www.tei-c.org/ns/1.0"
                xmlns:redirect="http://xml.apache.org/xalan/redirect"
                xmlns:xalan="http://xml.apache.org/xalan"
                xmlns:ehri="https://ehri-project.eu/functions"
                xmlns:func="http://exslt.org/functions"
                extension-element-prefixes="xalan redirect func ehri"
                exclude-result-prefixes="xhtml tei">
    <xsl:output indent="yes" omit-xml-declaration="yes" encoding="utf-8" method="xml" xalan:indent-amount="4"/>

    <func:function name="ehri:slugify">
        <xsl:param name="url"/>

        <xsl:variable name="ehri-auth" select="'https://portal.ehri-project.eu/authorities/'"/>
        <xsl:variable name="ehri-term" select="'https://portal.ehri-project.eu/keywords/'"/>
        <xsl:variable name="ehri-unit" select="'https://portal.ehri-project.eu/units/'"/>
        <xsl:variable name="ehri-inst" select="'https://portal.ehri-project.eu/institutions/'"/>
        <xsl:variable name="geonames1" select="'http://sws.geonames.org/'"/>
        <xsl:variable name="geonames2" select="'http://www.geonames.org/'"/>
        <xsl:variable name="holocaustcz" select="'https://www.holocaust.cz/databaze-obeti/obet/'"/>

        <func:result>
            <xsl:choose>
                <xsl:when test="starts-with($url, $ehri-auth)">
                    <xsl:value-of select="concat('authority-', substring-after($url, $ehri-auth))"/>
                </xsl:when>
                <xsl:when test="starts-with($url, $ehri-term)">
                    <xsl:value-of select="concat('keyword-', substring-after($url, $ehri-term))"/>
                </xsl:when>
                <xsl:when test="starts-with($url, $ehri-unit)">
                    <xsl:value-of select="concat('unit-', substring-after($url, $ehri-unit))"/>
                </xsl:when>
                <xsl:when test="starts-with($url, $ehri-inst)">
                    <xsl:value-of select="concat('inst-', substring-after($url, $ehri-inst))"/>
                </xsl:when>
                <xsl:when test="starts-with($url, $geonames1)">
                    <xsl:value-of select="concat('geonames-', substring-after($url, $geonames1))"/>
                </xsl:when>
                <xsl:when test="starts-with($url, $geonames2)">
                    <xsl:value-of select="concat('geonames-', substring-before(substring-after($url, $geonames2),'/'))"/>
                </xsl:when>
                <xsl:when test="starts-with($url, $holocaustcz)">
                    <xsl:value-of select="concat('holocaust-cz-', substring-after($url, $holocaustcz))"/>
                </xsl:when>
                <xsl:otherwise/>
            </xsl:choose>
        </func:result>
    </func:function>

    <xsl:template match="tei:place" name="place-entity">
        <xsl:variable name="link">
            <xsl:value-of select="./tei:linkGrp/tei:link[@type='normal']/@target"/>
        </xsl:variable>
        <div class="content-info-entity tei-entity tei-place">
            <xsl:attribute name="data-ref">
                <xsl:value-of select="$link"/>
            </xsl:attribute>
            <h5>
                <xsl:value-of select="./tei:placeName"/>
            </h5>
            <xsl:if test="./tei:location/tei:geo">
                <div class="content-info-entity-body">
                    <p><xsl:value-of select="./tei:location/tei:geo"/></p>
                </div>
            </xsl:if>
            <xsl:variable name="desc">
                <xsl:value-of select="./tei:linkGrp/tei:link[@type='desc']/@target"/>
            </xsl:variable>
            <div class="content-info-entity-footer">
                <xsl:if test="$link != ''">
                    <a target="_blank">
                        <xsl:attribute name="href">
                            <xsl:value-of select="$link"/>
                        </xsl:attribute>
                        <xsl:value-of select="$link"/>
                    </a>
                </xsl:if>
                <xsl:if test="$desc != ''">
                    <a class="tei-entity-description" target="_blank">
                        <xsl:attribute name="href">
                            <xsl:value-of select="$desc"/>
                        </xsl:attribute>
                        <xsl:value-of select="$desc"/>
                    </a>
                </xsl:if>
            </div>
        </div>
    </xsl:template>

    <xsl:template match="tei:person" name="person-entity">
        <xsl:variable name="link">
            <xsl:value-of select="./tei:linkGrp/tei:link[@type='normal']/@target"/>
        </xsl:variable>
        <div class="content-info-entity tei-entity tei-person">
            <xsl:attribute name="data-ref">
                <xsl:value-of select="$link"/>
            </xsl:attribute>
            <h5>
                <xsl:value-of select="./tei:persName"/>
            </h5>
            <xsl:if test="./tei:note">
                <div class="content-info-entity-body">
                    <xsl:copy-of select="./tei:note/node()"/>
                </div>
            </xsl:if>
            <xsl:variable name="desc">
                <xsl:value-of select="./tei:linkGrp/tei:link[@type='desc']/@target"/>
            </xsl:variable>
            <div class="content-info-entity-footer">
                <xsl:if test="$link != ''">
                    <a target="_blank">
                        <xsl:attribute name="href">
                            <xsl:value-of select="$link"/>
                        </xsl:attribute>
                        <xsl:value-of select="$link"/>
                    </a>
                </xsl:if>
                <xsl:if test="$desc != ''">
                    <a class="tei-entity-description" target="_blank">
                        <xsl:attribute name="href">
                            <xsl:value-of select="$desc"/>
                        </xsl:attribute>
                        <xsl:value-of select="$desc"/>
                    </a>
                </xsl:if>
            </div>
        </div>
    </xsl:template>

    <xsl:template match="tei:org" name="org-entity">
        <xsl:variable name="link">
            <xsl:value-of select="./tei:linkGrp/tei:link[@type='normal']/@target"/>
        </xsl:variable>
        <div class="content-info-entity tei-entity tei-org">
            <xsl:attribute name="data-ref">
                <xsl:value-of select="$link"/>
            </xsl:attribute>
            <h5>
                <xsl:value-of select="./tei:orgName"/>
            </h5>
            <xsl:if test="./tei:note">
                <div class="content-info-entity-body">
                    <xsl:copy-of select="./tei:note/node()"/>
                </div>
            </xsl:if>
            <xsl:variable name="desc">
                <xsl:value-of select="./tei:linkGrp/tei:link[@type='desc']/@target"/>
            </xsl:variable>
            <div class="content-info-entity-footer">
                <xsl:if test="$link != ''">
                    <a target="_blank">
                        <xsl:attribute name="href">
                            <xsl:value-of select="$link"/>
                        </xsl:attribute>
                        <xsl:value-of select="$link"/>
                    </a>
                </xsl:if>
                <xsl:if test="$desc != ''">
                    <a class="tei-entity-description" target="_blank">
                        <xsl:attribute name="href">
                            <xsl:value-of select="$desc"/>
                        </xsl:attribute>
                        <xsl:value-of select="$desc"/>
                    </a>
                </xsl:if>
            </div>
        </div>
    </xsl:template>

    <xsl:template match="tei:item" name="term-entity">
        <xsl:variable name="link">
            <xsl:value-of select="./tei:linkGrp/tei:link[@type='normal']/@target"/>
        </xsl:variable>
        <div class="content-info-entity tei-entity tei-item">
            <xsl:attribute name="data-ref">
                <xsl:value-of select="./tei:linkGrp/tei:link[@type='normal']/@target"/>
            </xsl:attribute>
            <h5>
                <xsl:value-of select="./tei:name"/>
            </h5>
            <xsl:if test="./tei:note">
                <div class="content-info-entity-body">
                    <xsl:copy-of select="./tei:note/node()"/>
                </div>
            </xsl:if>
            <xsl:variable name="desc">
                <xsl:value-of select="./tei:linkGrp/tei:link[@type='desc']/@target"/>
            </xsl:variable>
            <div class="content-info-entity-footer">
                <xsl:if test="$link != ''">
                    <a target="_blank">
                        <xsl:attribute name="href">
                            <xsl:value-of select="$link"/>
                        </xsl:attribute>
                        <xsl:value-of select="$link"/>
                    </a>
                </xsl:if>
                <xsl:if test="$desc != ''">
                    <a class="tei-entity-description" target="_blank">
                        <xsl:attribute name="href">
                            <xsl:value-of select="$desc"/>
                        </xsl:attribute>
                        <xsl:value-of select="$desc"/>
                    </a>
                </xsl:if>
            </div>
        </div>
    </xsl:template>


    <xsl:template match="tei:p" name="identity">
        <xsl:apply-templates select="node()|@*"/>
    </xsl:template>

    <xsl:template match="tei:term|tei:placeName|tei:persName|tei:orgName">
        <span class="tei-entity-ref">
            <xsl:attribute name="data-ref">
                <xsl:value-of select="attribute::ref"/>
            </xsl:attribute>
            <xsl:attribute name="data-neatline-slug">
                <xsl:value-of select="ehri:slugify(./attribute::ref)"/>
            </xsl:attribute>
            <xsl:apply-templates/>
        </span>
    </xsl:template>

    <xsl:template match="/">
        <div class="tei">
            <div class="tei-entities">
                <xsl:attribute name="class">tei-entities</xsl:attribute>
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
            </div>

            <div class="tei-text">
                <xsl:attribute name="class">tei-text</xsl:attribute>
                <xsl:for-each select="/tei:TEI/tei:text/tei:body/tei:p">
                    <p>
                        <xsl:apply-templates/>
                    </p>
                </xsl:for-each>
            </div>
        </div>
    </xsl:template>
</xsl:stylesheet>