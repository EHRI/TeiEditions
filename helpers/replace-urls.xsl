<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:tei="http://www.tei-c.org/ns/1.0"
                xmlns:ehri="http://ehri-project.eu"
                xmlns:php="http://php.net/xsl">

    <!-- Append <entry key="filename">path</entry> nodes here -->
    <ehri:url-lookup/>

    <xsl:template match="node()|@*">
        <xsl:copy>
            <xsl:apply-templates select="node()|@*"/>
        </xsl:copy>
    </xsl:template>

    <xsl:template match="tei:graphic/@url | tei:pb/@facs">
        <xsl:variable name="attr" select="name(.)"/>
        <xsl:variable name="filename" select="php:function('basename', string(.))"/>

        <xsl:variable name="replace" select="document('')/*/ehri:url-lookup/entry[@key=$filename]"/>
        <xsl:attribute name="{$attr}">
            <xsl:choose>
                <xsl:when test="$replace != ''">
                    <xsl:value-of select="$replace"/>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:value-of select="."/>
                </xsl:otherwise>
            </xsl:choose>
        </xsl:attribute>
    </xsl:template>
</xsl:stylesheet> 
