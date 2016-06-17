<?php
/**
 * Tests for the ApacheRedirectParser class.
 *
 * @author Steve Grunwell
 */

namespace SteveGrunwell\ApacheRedirectParser;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ApacheRedirectParserTest extends TestCase
{
	public function testExtractRewriteRules() {
		$instance = new ApacheRedirectParser;

		$method = new ReflectionMethod($instance, 'extractRewriteRules');
		$method->setAccessible(true);

		$contents = <<<EOT
RewriteRule /foo /bar [R=301,L]
RewriteRule /baz /foobar [L]
EOT;
		$this->assertCount(2, $method->invoke($instance, $contents));
	}

	public function testExtractRewriteRulesWithRewriteCond() {
		$instance = new ApacheRedirectParser;

		$method = new ReflectionMethod($instance, 'extractRewriteRules');
		$method->setAccessible(true);

		$contents = <<<EOT
RewriteCond %{REQUEST_FILENAME} -f
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule /foo /bar [R=301,L]
EOT;
		$this->assertEquals($contents, $method->invoke($instance, $contents)[0]);
	}

		public function testExtractRewriteRulesWithRewriteCondAndSurroundingRules() {
		$instance = new ApacheRedirectParser;

		$method = new ReflectionMethod($instance, 'extractRewriteRules');
		$method->setAccessible(true);

		$contents = <<<EOT
RewriteRule /baz /bar [R=301]
RewriteCond %{REQUEST_FILENAME} -f
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule /foo /bar [R=301,L]
RewriteRule /red /blue [R=301,L]
EOT;
		$this->assertCount(3, $method->invoke($instance, $contents));
	}

	public function testRewriteOutputKeys()
	{
		$rewrite = $this->parseRewrite('RewriteRule /foo /bar [R=301]');

		$this->assertArrayHasKey('path', $rewrite);
		$this->assertArrayHasKey('target', $rewrite);
		$this->assertArrayHasKey('statusCode', $rewrite);
	}

	public function testBasicRewrites()
	{
		$rewrite = $this->parseRewrite('RewriteRule /foo /bar');

		$this->assertEquals('/foo', $rewrite['path']);
		$this->assertEquals('/bar', $rewrite['target']);
	}

	public function testThatStatusCodeDefaultsTo302()
	{
		$rewrite = $this->parseRewrite('RewriteRule /foo /bar');

		$this->assertEquals(302, $rewrite['statusCode']);
	}

	public function testBasicRewritesWithModifiers()
	{
		$rewrite = $this->parseRewrite('RewriteRule /foo /bar [R=301]');

		$this->assertEquals(301, $rewrite['statusCode']);
	}

	/**
	 * Helper to avoid having to constantly set up a new ReflectionMethod for
	 * ApacheRedirectParser::parseRewrite().
	 *
	 * @return array The response from ApacheRedirectParser::parseRewrite().
	 */
	protected function parseRewrite($rewriteRule)
	{
		$instance = new ApacheRedirectParser;

		$method = new ReflectionMethod($instance, 'parseRewrite');
		$method->setAccessible(true);

		return $method->invoke($instance, $rewriteRule);
	}
}
