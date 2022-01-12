<?php

namespace EDevlet;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Smalot\PdfParser\Parser;

/**
 * Class Dogrula
 * @package EDevlet
 */
class Dogrula
{

	/**
	 * Validation steps
	 * @var array
	 */
	protected $steps = array(
		'parse_pdf',
		'get_token',
		'accept_form',
	);

	/**
	 * Endpoint
	 * @var string
	 */
	protected $endpoint = 'https://www.turkiye.gov.tr';

	/**
	 * Version
	 * @var string
	 */
	public $version = '1.1.0';

	/**
	 * File to be validated
	 * @var string
	 */
	private $file;

	/**
	 * Kimlik no
	 * @var string
	 */
	private $kimlikNo;

	private $isimSoyisim;

	/**
	 * Pdf Body
	 * @var string
	 */
	private $pdfBody;

	/**
	 * Barkod
	 * @var string
	 */
	private $barkod;

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @var string
	 */
	private $responseBody;

	/**
	 * Token
	 * @var string
	 */
	private $token;

	/**
	 * Verbose
	 * @var bool
	 */
	public $verbose = false;


	/**
	 * Doğrulama
	 * @param string $kimlikNo
	 * @param string $file
	 *
	 * @return bool|mixed
	 */
	public function dogrula($file)
	{
		$this->file = $file;
		$result = false;

		// Run steps
		foreach ($this->steps as $step) {
			try {
				$result = call_user_func(array($this, $step));
				if ($result !== true) {
					break;
				}
			} catch (ConnectException $e) {
				$result = null;
				break;
			} catch (\Exception $e) {
				$result = false;
				break;
			}
		}
		// Return result
		return $result;
	}

	function getPart($regex, $string)
	{
		$m = array();
		preg_match($regex, $string, $m);
		return $m && isset($m[1]) ? $m[1] : null;
	}

	function transliterateTurkishChars($inputText) {
    $search  = array('ç', 'Ç', 'ð', 'Ð', 'ý', 'Ý', 'ö', 'Ö', 'þ', 'Þ', 'ü', 'Ü');
    $replace = array('c', 'C', 'g', 'G', 'i', 'I', 'o', 'O', 's', 'S', 'u', 'U');
    $outputText=str_replace($search, $replace, $inputText);
    return $outputText;
	}

	private function parse_pdf()
	{
		$this->pdfBody = str_replace("\r\n", "\n", (string)(new Parser())->parseFile($this->file)->getText());
		$this->pdfBody = $this->transliterateTurkishChars($this->pdfBody);
		$pdf = explode("\n", $this->pdfBody);
		$this->isimSoyisim = substr(str_ireplace("Adi / Soyadi"," ",$pdf[8]),2,14);
		$this->kimlikNo = $this->getPart('/(\d+)\sT.C. Kimlik No/', $this->pdfBody);
		$this->barkod = reset($pdf);
		echo $this->barkod;
		if (strpos($this->pdfBody, $this->kimlikNo) === false) {
			return false;
		}
		if (empty($this->barkod) || !is_string($this->barkod)) {
			return false;
		}
		return true;
	}

	public function get_kimlikNo(){
		return $this->kimlikNo;
	}

	public function get_adSoyad(){
		return $this->isimSoyisim;
	}

	private function get_token()
	{
		// Create client
		$this->client = new Client(array(
			'base_uri' => $this->endpoint,
			'timeout' => 5,
			'cookies' => true,
			'headers' => array(
				'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36'
			)
		));
		// Get token
		$resp = (string) $this->client->get('/belge-dogrulama')->getBody();
		$this->token = trim($this->getPart('~data-token="(.+?)"~', $resp));

		if (!$this->token || empty($this->token)) {
			return false;
		}
		return true;
	}

	private function accept_form()
	{
		$resp = (string) $this->client->post('/belge-dogrulama?submit', array(
			'form_params' => array(
				'sorgulananTCKimlikNo' => $this->kimlikNo,
				'sorgulananBarkod' => $this->barkod,
				'token' => $this->token
			)
		))->getBody();

		$this->token = trim($this->getPart('~data-token="(.+?)"~', $resp));
		$this->responseBody = (string) $this->client->post('/belge-dogrulama?asama=kontrol&submit', array(
			'form_params' => array(
				'chkOnay' => 1,
				'token' => $this->token
			)
		))->getBody();

		return true;
	}
}
