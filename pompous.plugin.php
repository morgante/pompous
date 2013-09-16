<?php
class pompous extends Plugin {
	/**
	 * Build the configuration settings
	 */
	public function configure()
	{
		$ui = new FormUI( 'pompous_config' );

		// Add a text control for the address you want the email sent to
		$xml = $ui->append( 'text', 'xml', 'option:pompous__xmlurl', _t( 'XML URL: ' ) );
		$xml->add_validator( 'validate_required' );
		
		$ui->append( 'submit', 'save', 'Save' );
		return $ui;
	}
}

require_once('experience.php');
?>