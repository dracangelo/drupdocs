<?php

namespace Drupal\log_stdout\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Psr\Log\LoggerInterface;

/**
 * This class allows logging to stdout and stderr.
 */
class Stdout implements LoggerInterface {

  use RfcLoggerTrait;

  /**
   * A configuration object containing settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The message's placeholders parser.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $parser;

  /**
   * Constructs a Stdout object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory object.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LogMessageParserInterface $parser) {
    $this->config = $config_factory->get('log_stdout.settings');
    $this->parser = $parser;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    global $base_url;

    if ($level > $this->config->get('severity_level')) {
      // Severity level is above the ones we want to log.
      return;
    }

    if ($this->config->get('use_stderr') == '1' && $level <= RfcLogLevel::WARNING) {
      $output = fopen('php://stderr', 'w');
    }
    else {
      $output = fopen('php://stdout', 'w');
    }

    $severity = strtoupper(RfcLogLevel::getLevels()[$level]);

    // Populate the message placeholders and then replace them in the message.
    $variables = $this->parser->parseMessagePlaceholders($message, $context);
    $message = empty($variables) ? $message : strtr($message, $variables);

    $fmt = $this->config->get('format');
    if (empty($fmt)) {
      $fmt = '[@severity] [@type] [@date] @message | uid: @uid | request-uri: @request_uri | refer: @referer | ip:  @ip | link: @link';
    }

    $entry = strtr($fmt, [
      '@base_url'    => $base_url,
      '@timestamp'   => $context['timestamp'],
      '@severity'    => $severity,
      '@type'        => $context['channel'],
      '@message'     => strip_tags($message),
      '@uid'         => $context['uid'],
      '@request_uri' => $context['request_uri'],
      '@referer'     => $context['referer'],
      '@ip'          => $context['ip'],
      '@link'        => strip_tags($context['link']),
      '@date'        => date('Y-m-d\TH:i:s', $context['timestamp']),
    ]);

    fwrite($output, $entry . "\r\n");
    fclose($output);
  }

}
