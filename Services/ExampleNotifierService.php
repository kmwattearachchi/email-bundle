<?php
namespace Azine\EmailBundle\Services;

use Azine\EmailBundle\Entity\RecipientInterface;

/**
 * This Service compiles and renders the emails to be sent.
 * This class is only an example. Implement your own!
 * @codeCoverageIgnoreStart
 * @author Dominik Businger
 */
class ExampleNotifierService extends AzineNotifierService {

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.AzineNotifierService::getRecipientVarsForNotificationsEmail()
	 */
	protected function getRecipientVarsForNotificationsEmail(RecipientInterface $recipient){
		$recipientParams = parent::getRecipientVarsForNotificationsEmail($recipient);

		// $recipientParams = array_merge($this->getSomeMoreRecipientParamsForTheNotificationEmail($recipient), $recipientParams);

		return $recipientParams;
	}

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.AzineNotifierService::getNonRecipientSpecificNewsletterContentItems()
	 */
	protected function getNonRecipientSpecificNewsletterContentItems(){
		$contentItems = array();

		// $templateParams = array('title' => 'foo bar');
		// $templateParams = array_merge($this->getSomeMoreParamsForTheNewsletter(), $templateParams)
		// $contentItems[] = array('AcmeBundle:foo:barSameForAllRecipientsTemplate', $templateParams);

		return $contentItems;
	}

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.AzineNotifierService::getRecipientSpecificNewsletterContentItems()
	 */
	protected function getRecipientSpecificNewsletterContentItems(RecipientInterface $recipient){
		$contentItems = array();

		// $recipientSpecificTemplateParams = array('title' => 'foo bar');
		// $recipientSpecificTemplateParams = array_merge($this->getSomeMoreParamsForTheNewsletterForThisRecipient($recipient), $recipientSpecificTemplateParams)
		// $contentItems[] = array('AcmeBundle:foo:barDifferentForEachRecipientTemplate', $recipientSpecificTemplateParams);

		return $contentItems;
	}


}
