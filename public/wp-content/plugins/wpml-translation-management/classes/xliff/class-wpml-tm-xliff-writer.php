<?php
/**
 * @package wpml-core
 */

class WPML_TM_Xliff_Writer extends WPML_TM_Job_Factory_User {

	private $xliff_version;

	/**
	 * WPML_TM_xliff constructor.
	 *
	 * @param WPML_Translation_Job_Factory $job_factory
	 * @param string                       $xliff_version
	 */
	public function __construct( &$job_factory, $xliff_version = TRANSLATION_PROXY_XLIFF_VERSION ) {
		parent::__construct( $job_factory );
		$this->xliff_version = $xliff_version;
	}

	/**
	 * Generate a XLIFF file for a given job.
	 *
	 * @param int $job_id
	 *
	 * @return resource XLIFF representation of the job
	 */
	public function get_job_xliff_file( $job_id ) {

		return $this->generate_xliff_file( $this->generate_job_xliff( $job_id ) );
	}

	/**
	 * Generate a XLIFF string for a given post or external type (e.g. package) job.
	 *
	 * @param int $job_id
	 *
	 * @return string XLIFF representation of the job
	 */
	public function generate_job_xliff( $job_id ) {
		global $iclTranslationManagement;

		// don't include not-translatable and don't auto-assign
		$job               = $iclTranslationManagement->get_translation_job( (int) $job_id, false, false, 1 );
		$translation_units = $this->get_job_translation_units( $job );
		$original          = $job_id . '-' . md5( $job_id . $job->original_doc_id );

		$xliff = $this->generate_xliff( $original,
			$job->source_language_code,
			$job->language_code,
			$translation_units );

		return $xliff;
	}

	/**
	 * Generate a XLIFF file for a given set of strings.
	 *
	 * @param array  $strings
	 * @param string $source_language
	 * @param string $target_language
	 *
	 * @return resource XLIFF file
	 */
	public function get_strings_xliff_file( $strings, $source_language, $target_language ) {

		return $this->generate_xliff_file(
			$this->generate_xliff(
				uniqid(),
				$source_language,
				$target_language,
				$this->generate_strings_translation_units( $strings ) )
		);
	}

	private function generate_xliff( $original_id, $source_language, $target_language, $translation_units ) {
		// Keep unindented to generate a pretty printed xml
		$xliff = "";
		$xliff .= '<?xml version="1.0" encoding="utf-8" standalone="no"?>';
		$xliff .= $this->get_xliff_opening( $this->xliff_version );
		$xliff .= "\t" . '<file original="' . $original_id . '" source-language="' . $source_language . '" target-language="' . $target_language . '" datatype="plaintext">';
		$xliff .= "\t" . "\t" . '<header />' . "\n";
		$xliff .= "\t" . "\t" . '<body>' . "\n";
		$xliff .= "\t" . "\t" . "\t" . $translation_units . "\n";
		$xliff .= "\t" . "\t" . '</body>' . "\n";
		$xliff .= "\t" . '</file>' . "\n";
		$xliff .= "</xliff>" . "\n";

		return $xliff;
	}

	private function get_xliff_opening( $xliff_version ) {
		switch ( $xliff_version ) {
			case '10':
				$xliff = '<!DOCTYPE xliff PUBLIC "-//XLIFF//DTD XLIFF//EN" "http://www.oasis-open.org/committees/xliff/documents/xliff.dtd">' . PHP_EOL;
				$xliff .= '<xliff version="1.0">' . "\n";
				break;
			case '11':
				$xliff = '<xliff version="1.1" xmlns="urn:oasis:names:tc:xliff:document:1.1">' . "\n";
				break;
			case '12':
			default:
				$xliff = '<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">' . "\n";
				break;
		}

		return $xliff;
	}

	/**
	 * Generate translation units for a given set of strings.
	 *
	 * The units are the actual content to be translated
	 * Represented as a source and a target
	 *
	 * @param array $strings
	 *
	 * @return string The translation units representation
	 */
	private function generate_strings_translation_units( $strings ) {
		$translation_units = '';
		foreach ( $strings as $string ) {
			$id = 'string-' . $string->id;
			$translation_units .= $this->get_translation_unit( $id, "string", $string->value, $string->value );
		}

		return $translation_units;
	}

	/**
	 * Generate translation units.
	 *
	 * The units are the actual content to be translated
	 * Represented as a source and a target
	 *
	 * @param object $job
	 *
	 * @return string The translation units representation
	 */
	private function get_job_translation_units( $job ) {
		$translation_units = '';
		foreach ( $job->elements as $element ) {
			if ( $element->field_translate == '1' ) {
				$field_data_translated = base64_decode( $element->field_data_translated );
				$field_data            = base64_decode( $element->field_data );
				if ( substr( $element->field_type, 0, 6 ) === 'field-' ) {
					$field_data_translated = apply_filters( 'wpml_tm_xliff_export_translated_cf', $field_data_translated, $element );
					$field_data            = apply_filters( 'wpml_tm_xliff_export_original_cf', $field_data, $element );
				}
				// check for untranslated fields and copy the original if required.
				if ( ! isset( $field_data_translated ) || $field_data_translated == '' ) {
					$field_data_translated = $field_data;
				}
				if ( $field_data != '' ) {
					$translation_units .= $this->get_translation_unit( $element->field_type, $element->field_type, $field_data, $field_data_translated );
				}
			}
		}

		return $translation_units;
	}

	private function get_translation_unit( $field_id, $field_name, $field_data, $field_data_translated ) {
		global $sitepress;

		$translation_unit = "";
		if ( $sitepress->get_setting( 'xliff_newlines' ) == WPML_XLIFF_TM_NEWLINES_REPLACE ) {
			$field_data            = str_replace( "\n", '<br class="xliff-newline" />', $field_data );
			$field_data_translated = str_replace( "\n", '<br class="xliff-newline" />', $field_data_translated );
		}
		$translation_unit .= '         <trans-unit resname="' . $field_name . '" restype="string" datatype="html" id="' . $field_id . '">' . "\n";
		$translation_unit .= '            <source><![CDATA[' . $field_data . ']]></source>' . "\n";
		$translation_unit .= '            <target><![CDATA[' . $field_data_translated . ']]></target>' . "\n";
		$translation_unit .= '         </trans-unit>' . "\n";

		return $translation_unit;
	}

	/**
	 * Save a xliff string to a temporary file and return the file ressource
	 * handle
	 *
	 * @param string $xliff_content
	 *
	 * @return resource XLIFF
	 */
	private function generate_xliff_file( $xliff_content ) {
		$file = fopen( 'php://temp', 'r+' );
		fwrite( $file, $xliff_content );
		rewind( $file );

		return $file;
	}
}
