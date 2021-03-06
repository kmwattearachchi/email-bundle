<?php
namespace Azine\EmailBundle\Services;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * You must override this service for your needs. Also see ExampleAzineWebViewSerive for some examples.
 *
 * @author dominik
 */
class AzineWebViewService implements WebViewServiceInterface
{
	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.WebViewServiceInterface::getTemplatesForWebView()
	 */
	public function getTemplatesForWebView(){
		$templates = array();
		// override this method to add your own templates
		// $templates =$this->addTemplate($templates, "Some other mail",	ExampleTemplateProvider::SOME_OTHER_MAIL_TEMPLATE);
		// $templates =$this->addTemplate($templates, "VIP Infos",	ExampleTemplateProvider::VIP_INFO_MAIL_TEMPLATE);

		return $templates;
	}

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.WebViewServiceInterface::getTestMailAccounts()
	 */
	public function getTestMailAccounts(){
		$emails = array();
		// override this method to add your own emails
		// $emails = $this->addTestMailAccount($emails, 'Testmail-account for MS Outlook',	'your.email.address@for.an.outlook.client.com');
		// $emails = $this->addTestMailAccount($emails, 'Testmail-account for Gmail', 	'your.email.address@gmail');
		return $emails;
	}

	/**
	 * (non-PHPdoc)
	 * @see Azine\EmailBundle\Services.WebViewServiceInterface::getDummyVarsFor()
	 */
	public function getDummyVarsFor($template, $locale){
		$variables = array();
		$variables['sendMailAccountName'] = "some name";
		$variables['sendMailAccountAddress'] = "no-reply@email.com";

		// override this method to provide dummy-variables
		// to view rendered templates for emails that you didn't send yet
		// or to send an email with dummy-variables to your test-account(s)
		//
		// do something like this:
		//
		// if($template == ExampleTemplateProvider::VIP_INFO_MAIL_TEMPLATE){
		// 	$vipVars = array();
		// 	$vipVars['vipInfos'] = $someService->getVipInfosFor($aUser);
		// 	$vipVars['userTitle'] = "You majesty";
		// 	$variables['contentItems'][] = array(ExampleTemplateProvider::VIP_INFO_MAIL_TEMPLATE, $vipVars);

		// } else if ($template == ExampleTemplateProvider::SOME_OTHER_MAIL_TEMPLATE){
		// 	$otherMailVars = array();
		// 	$otherMailVars['date'] = new \DateTime("long ago");
		// 	$variables['contentItems'][] = array(ExampleTemplateProvider::SOME_OTHER_MAIL_TEMPLATE, $otherMailVars);
		// }

		return $variables;
	}

	/**
	 * @param UrlGeneratorInterface $router
	 */
	public function __construct(	UrlGeneratorInterface $router){
		$this->router = $router;
	}

//////////////////////////////////////////////////////////////////////////
/* You probably don't need to change or override any of the stuff below */
//////////////////////////////////////////////////////////////////////////

	/**
	 * @var UrlGeneratorInterface
	 */
	protected $router;


	/**
	 * Add an email of a test account, you might want to send html-emails to, to verify the template before sending the emails to "real" recipients/users.
	 * @param array $emails
	 * @param string $description
	 * @param string $emailAddress
	 * @return array the array with the added email-test-account
	 */
	protected function addTestMailAccount(array $emails, $description, $emailAddress){
		$emails[] = array('accountDescription' => $description, 'accountEmail' => $emailAddress );
		return $emails;
	}

	/**
	 * Add the required variables to the $templates-array, so the line can be rendered in the template-index
	 * @param unknown_type $templates
	 * @param unknown_type $description
	 * @param unknown_type $templateId
	 * @param unknown_type $formats
	 * @return multitype:unknown
	 */
	protected function addTemplate($templates, $description, $templateId, $formats = array('txt','html')){

		$route = $this->router->generate("azine_email_web_preview", array('template' => $templateId));

		$template = array(	'url' 			=> $route,
				'description'	=> $description,
				'formats' 		=> $formats,
				'templateId'	=> $templateId,
		);

		$templates[] = $template;

		return $templates;
	}


}
