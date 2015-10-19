<?php
/**
 * @file
 * Contains \Drupal\flood_control\Form\AdminForm.
 */

namespace Drupal\flood_control\Form;

use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Datetime\Entity\DateFormat; 
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Flood Control System settings form.
 */
class AdminForm extends ConfigFormBase {

	/**
	 * Constructs a \Drupal\system\ConfigFormBase object.
	 *
	 * @param \Drupal\Core\Flood\FloodInterface $flood
	 *   The flood service.
	 * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
	 *   The factory for configuration objects.
	 *
	 */
	public function __construct(FloodInterface $flood, ConfigFactoryInterface $config_factory) {
		$this->configFactory = $config_factory;
		$this->flood = $flood;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container) {
		return new static(
			$container->get('flood'),
			$container->get('config.factory')
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getFormID() {
		return 'flood_control_admin_form';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getEditableConfigNames() {
		return array('user.flood', 'contact.settings');
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, FormStateInterface $form_state) {

		// Get the current user
		$user = \Drupal::currentUser();
		$flood_config = $this->config('user.flood');
		$contact_flood_config = $this->config('contact.settings');

		// User module flood events.
		$form['login'] = array(
			'#type' => 'fieldset',
			'#title' => $this->t('Login'),
			'#access' => $user->hasPermission('administer users'),
		);

		$form['login']['user_failed_login_ip_limit'] = array(
			'#type' => 'select',
			'#title' => t('Failed login (IP) limit'),
			'#options' => $this->getMapAssoc(),
			'#default_value' => $flood_config->get('ip_limit'),
		);

		$form['login']['user_failed_login_ip_window'] = array(
			'#type' => 'select',
			'#title' => t('Failed login (IP) window'),
			'#options' => $this->getDateFormatedList(TRUE),
			'#default_value' => $flood_config->get('ip_window'),
		);
		$form['login']['user_failed_login_user_limit'] = array(
			'#type' => 'select',
			'#title' => t('Failed login (username) limit'),
			'#options' => $this->getMapAssoc(),
			'#default_value' => $flood_config->get('user_limit'),
		);
		$form['login']['user_failed_login_user_window'] = array(
			'#type' => 'select',
			'#title' => t('Failed login (username) window'),
			'#options' => $this->getDateFormatedList(TRUE),
			'#default_value' => $flood_config->get('user_window'),
		);

		// Contact module flood events.
		$form['contact'] = array(
			'#type' => 'fieldset',
			'#title' => t('Contact forms'),
			'#access' => $user->hasPermission('administer contact forms'),
		);
		$form['contact']['contact_threshold_limit'] = array(
			'#type' => 'select',
			'#title' => t('Sending e-mails limit'),
			'#options' => $this->getMapAssoc(),
			'#default_value' => $contact_flood_config->get('flood.limit'),
		);
		$form['contact']['contact_threshold_window'] = array(
			'#type' => 'select',
			'#title' => t('Sending e-mails window'),
			'#options' => $this->getDateFormatedList(TRUE),
			'#default_value' => $contact_flood_config->get('flood.interval'),
		);

		return parent::buildForm($form, $form_state);
	}

	/**
	 * {@inheritdoc}
	 */
	public function validateForm(array &$form, FormStateInterface $form_state) {
	}

	/**
	* {@inheritdoc}
	*/
	public function submitForm(array &$form, FormStateInterface $form_state) {

		// User module flood events.
		$config = $this->configFactory->getEditable('user.flood');

		// Set the user failed login ip limit.
		if ($form_state->hasValue('user_failed_login_ip_limit')) {
			$config->set('ip_limit', $form_state->getValue('user_failed_login_ip_limit'));
		}
		// Set the user failed login ip window.
		if ($form_state->hasValue('user_failed_login_ip_window')) {
			$config->set('ip_window', $form_state->getValue('user_failed_login_ip_window'));
		}
		// Set the user failed login user limit.
		if ($form_state->hasValue('user_failed_login_user_limit')) {
			$config->set('user_limit', $form_state->getValue('user_failed_login_user_limit'));
		}
		// Set the user failed login user window.
		if ($form_state->hasValue('user_failed_login_user_window')) {
			$config->set('user_window', $form_state->getValue('user_failed_login_user_window'));
		}
		// Finally save the user module flood events configuration.
		$config->save();

		// Contact module flood events.
		$config = $this->configFactory->getEditable('contact.settings');

		// Set the contact threshold limit.
		if ($form_state->hasValue('contact_threshold_limit')) {
			$config->set('flood.limit', $form_state->getValue('contact_threshold_limit'));
		}
		// Set the contact threshold window.
		if ($form_state->hasValue('contact_threshold_window')) {
			$config->set('flood.interval', $form_state->getValue('contact_threshold_window'));
		}
		// Finally save the contact module flood events configuration.
		$config->save();
		drupal_set_message($this->t('The configuration options have been saved.'));
	}

	/**
	 * Converting the timestamps into a date formats.
	 *
	 * @param bool $showSelect
	 *   If TRUE, a "None (disabled)" entry is added as the first entry.
	 *
	 * @return array
	 *   Associative array with all date time formats:
	 *   - value: date format
	 */
	protected function getDateFormatedList($showSelect = FALSE) {

		$date_formatter = \Drupal::service('date.formatter');
		$timestamps = array(60, 180, 300, 600, 900, 1800, 2700, 3600, 10800, 21600, 32400, 43200, 86400);
		$list = array();

		// Add the "None (disabled)" as first entry.
		if (filter_var($showSelect, FILTER_VALIDATE_BOOLEAN)) {
			$list['0'] = $this->t('None (disabled)');
		}

		// Append all timestamps.
		foreach ($timestamps as $e) {
			$list[$e] = $date_formatter->formatInterval($e);
		}
		return $list;
	}

	/**
	 * Forms an associative array from a linear array.
	 *
	 * @return array
	 *   Associative array.
	 */
	protected function getMapAssoc() {

		$array = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 20, 30, 40, 50, 75, 100, 125, 150, 200, 250, 500);
		$array = !empty($array) ? array_combine($array, $array) : array();
		return $array;
	}
}
