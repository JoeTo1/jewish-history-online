<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:tei="http://www.tei-c.org/ns/1.0"
  exclude-result-prefixes="tei"
  version="2.0">

  <!-- we do separate note-handling -->
  <xsl:template match="tei:text[not(descendant::tei:text)]">
    <xsl:apply-templates/>
    <!--
    <xsl:if test='//tei:note[@place="foot"]'>
      <div class="dta-footnotesep"/>
      <xsl:apply-templates select='//tei:note[@place="foot" and text()]' mode="footnotes"/>
    </xsl:if>
    <xsl:apply-templates select='//tei:fw[@place="bottom" and (text() or *)]' mode="signatures"/>
    -->
  </xsl:template>

  <!-- add shared overrides and extensions to the dtabf-base rules -->
  <xsl:template match="tei:sic">
    <xsl:apply-templates/> [sic]
  </xsl:template>

  <xsl:template match='tei:persName'>
    <xsl:call-template name="entity-ref">
      <xsl:with-param name="value">
        <xsl:value-of select="@ref"/>
      </xsl:with-param>
      <xsl:with-param name="type">person</xsl:with-param>
    </xsl:call-template>
</xsl:template>

<xsl:template match='tei:placeName'>
    <xsl:call-template name="entity-ref">
      <xsl:with-param name="value">
        <xsl:value-of select="@ref"/>
      </xsl:with-param>
      <xsl:with-param name="type">place</xsl:with-param>
    </xsl:call-template>
</xsl:template>

<xsl:template match='tei:orgName'>
    <xsl:call-template name="entity-ref">
      <xsl:with-param name="value">
        <xsl:value-of select="@ref"/>
      </xsl:with-param>
      <xsl:with-param name="type">organization</xsl:with-param>
    </xsl:call-template>
</xsl:template>

<xsl:template name="entity-ref">
  <xsl:param name="value"/>
  <xsl:param name="type"/>
  <xsl:choose>
    <xsl:when test="$value and starts-with($value,'http')">
      <xsl:element name="span">
        <xsl:attribute name="class">entity-ref</xsl:attribute>
        <xsl:attribute name="data-type"><xsl:value-of select="$type" /></xsl:attribute>
        <xsl:attribute name="data-uri">
          <xsl:choose>
            <xsl:when test="contains($value,' ')">
              <xsl:value-of select="substring-before($value,' ')"/>
            </xsl:when>
            <xsl:otherwise>
              <xsl:value-of select="$value"/>
            </xsl:otherwise>
          </xsl:choose>
        </xsl:attribute>
        <xsl:apply-templates/>
      </xsl:element>
    </xsl:when>
    <xsl:otherwise>
      <xsl:apply-templates/>
    </xsl:otherwise>
  </xsl:choose>
</xsl:template>

</xsl:stylesheet>