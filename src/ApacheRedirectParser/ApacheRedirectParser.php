<?php
/**
 * Apache Redirect Parser library.
 *
 * @author Steve Grunwell
 */

namespace SteveGrunwell\ApacheRedirectParser;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class ApacheRedirectParser extends Command
{

	/**
	 * Define what the command should be called and what arguments it should accept.
	 */
	protected function configure()
	{
		$this
			->setName('parse')
			->setDescription('Parse an Apache2 VirtualHost or Htaccess file for rewrites rules.')
			->addArgument('file', InputArgument::REQUIRED, 'The file to parse.');
	}

	/**
	 * Execute the command.
	 *
	 * @param InputInterface  $input  The input interface.
	 * @param OutputInterface $output The output interface.
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$file = $input->getArgument('file');
		$rules = [];

		// Make sure we can open the file.
		if (! file_exists($file) || ! is_readable($file)) {
			$output->writeln('<error>The provided file cannot be opened for parsing.</error>');
			exit(1);
		}

		// Read the file contents to a string.
		try {
			$contents = $this->readFile($file);

		} catch (\Exception $e) {
			$output->writeln(sprintf(
				'<error>Unable to read file: %s</error>',
				$e->getMessage()
			));
			exit(1);
		}

		// Iterate through the matches.
		foreach ($this->extractRewriteRules($contents) as $rule) {
			$rules[] = $this->parseRewrite($rule);
		}

		$table = new Table($output);
		$table
			->setHeaders(['Path', 'Target', 'Status Code'])
			->setRows($rules)
			->render();
	}

	/**
	 * Read the contents of an Apache configuration file.
	 *
	 * @param string $file The system path for a file to parse.
	 * @return string $contents The file contents.
	 */
	protected function readFile($file)
	{
		$contents = '';

		try {
			$fh = fopen($file, 'rb');
			$contents = fread($fh, filesize($file));
			fclose($fh);

		} catch (\Exception $e) {
			if (is_resource($fh)) {
				fclose($fh);
			}

			throw $e;
		}

		return $contents;
	}

	/**
	 * Parse rewrite rules out of a given file.
	 *
	 * @param string $content The contents of a configuration file.
	 * @return array An array of RewriteRules found.
	 */
	protected function extractRewriteRules($content)
	{
		$regex = '/((?:RewriteCond(?:.|\s)+?)*RewriteRule.+)$/im';

		preg_match_all($regex, $content, $matches);

		return $matches ? $matches[0] : array();
	}

	/**
	 * Parse a single rewrite.
	 *
	 * @param string $rewriteRule The rewrite rule.
	 * @return array Information about the rewrite rule, containing (in order): path, destination,
	 *               status code.
	 */
	protected function parseRewrite($rewriteRule)
	{
		$keys = ['path', 'target', 'statusCode'];
		$rewrite = array_fill_keys($keys, null);

		/*
		 * Unless specified otherwise, Apache will use a 302 status code for redirects.
		 * @link https://httpd.apache.org/docs/current/rewrite/flags.html#flag_r
		 */
		$rewrite['statusCode'] = 302;

		// Get the basic components: path, target, and modifiers.
		$regex = '/RewriteRule (\S+) (\S+)(?:\s\[(.+?)\])?/i';
		if (preg_match($regex, $rewriteRule, $parts)) {
			$rewrite['path'] = $parts[1];
			$rewrite['target'] = $parts[2];

			if (isset($parts[3])) {
				parse_str($parts[3], $modifiers);

				if (isset($modifiers['R']) && $modifiers['R']) {
					$rewrite['statusCode'] = intval($modifiers['R']);
				}
			}
		}

		return $rewrite;
	}
}
