<?php
namespace Azine\EmailBundle\Controller;

use Azine\EmailBundle\Services\AzineTwigSwiftMailer;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

use Symfony\Component\HttpFoundation\RedirectResponse;

use Azine\EmailBundle\Services\ExampleTemplateProvider;
use FOS\UserBundle\Entity\User;
use Azine\EmailBundle\Entity\SentEmail;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\DependencyInjection\ContainerAware;
use Azine\EmailBundle\Services\TemplateProviderInterface;

/**
 * This controller provides the following actions:
 *
 * index: view a list of all your templates with the option to send a test mail with "dummy"-data to an email-address of your choice (see WebViewServiceInterface::getTemplatesForWebView() & WebViewServiceInterface::getTestMailAccounts) .
 * webPreView: shows the selected html- or txt-email-template filled with the dummy-data you defined (in the WebViewServiceInterface::getDummyVarsFor() function).
 * webView: shows an email that has been sent (and stored as SentEmail-entity in the database)
 * sendTestMail: sends an email filled with the dummy-data you defined to the selected email-address.
 * serveImage: serve an image from the template-image-folder
 *
 * @author dominik
 */
class AzineEmailTemplateController extends ContainerAware{

	/**
	 * Show a set of options to view html- and text-versions of email in the browser and send them as emails to test-accounts
	 */
	public function indexAction(){
		$customEmail = $this->container->get('request')->get('customEmail', 'custom@email.com');
		$templates = $this->container->get('azine_email_web_view_service')->getTemplatesForWebView();
		$emails = $this->container->get('azine_email_web_view_service')->getTestMailAccounts();

		return $this->container->get('templating')->renderResponse("AzineEmailBundle:Webview:index.html.twig",
																	 array(	'customEmail'	=> $customEmail,
																			'templates'		=> $templates,
																			'emails'		=> $emails,
																	 		));
	}

	/**
	 * Show a web-preview-version of an email-template, filled with dummy-content
	 */
	public function webPreViewAction($template, $format = null){
		if(!$format){
			$format = "html";
		}
		$locale = $this->container->get('request')->getLocale();

		$emailVars = $this->container->get('azine_email_web_view_service')->getDummyVarsFor($template, $locale);

		// add the styles
		$emailVars = $this->getTemplateProviderService()->addTemplateVariablesFor($template, $emailVars);

		// add the from-email for the footer-text
		if(!array_key_exists('fromEmail', $emailVars)){
			$noReply = $this->container->getParameter('azine_email_no_reply');
			$emailVars['fromEmail'] = $noReply['email'];
			$emailVars['fromName'] = $noReply['name'];
		}

		// set the emailLocale for the templates
		$emailVars['emailLocale'] = $locale;

		// replace absolute image-paths with relative ones.
		$emailVars = $this->getTemplateProviderService()->makeImagePathsWebRelative($emailVars, $locale);

		// add code-snippets
		$emailVars = $this->getTemplateProviderService()->addTemplateSnippetsWithImagesFor($template, $emailVars, $this->container->get('request')->getLocale());

		// render & return email
		$response = $this->renderResponse("$template.$format.twig", $emailVars);

		// if the requested format is txt, remove the html-part
		if($format == "txt"){
			// set the correct content-type
			$response->headers->set("Content-Type","text/plain");

			// cut away the html-part
			$content = $response->getContent();
			$textEnd = stripos($content, "<!doctype");
			$response->setContent(substr($content, 0, $textEnd));
		}
		return $response;
	}

	/**
	 * Show a web-version of an email that has been sent to recipients and has been stored in the database.
	 */
	public function webViewAction($token){
		$emailVars = array();

		// find email recipients, template & params
		$sentEmail = $this->getSentEmailForToken($token);

		// check if the sent email is available
		if($sentEmail != null){

			// check if the current user is allowed to see the email
			if ($this->userIsAllowedToSeeThisMail($sentEmail)){

				$template = $sentEmail->getTemplate();
				$emailVars = $sentEmail->getVariables();

				// re-attach all entities to the EntityManager.
				$this->reAttachAllEntities($emailVars);

				// remove the web-view-token from the param-array
				unset($emailVars[$this->getTemplateProviderService()->getWebViewTokenId()]);

				// render & return email
				$response = $this->renderResponse("$template.html.twig", $emailVars);
				return $response;

			// if the user is not allowed to see this mail
			} else {
				$msg = $this->container->get('translator')->trans('web.pre.view.test.mail.access.denied');
				throw new AccessDeniedException($msg);
			}
		}

		// the parameters-array is null => the email is not available in webView
		$days = $this->container->getParameter("azine_email_web_view_retention");
		$response = $this->renderResponse("AzineEmailBundle:Webview:mail.not.available.html.twig", array('days' => $days));
		$response->setStatusCode(404);

		return $response;
	}

	/**
	 * Check if the user is allowed to see the email.
	 * => the mail is public or the user is among the recipients or the user is an admin.
	 *
	 * @param SentEmail $mail
	 * @return boolean
	 */
	private function userIsAllowedToSeeThisMail(SentEmail $mail){
		$currentUser = $this->getUser();
		$recipients = $mail->getRecipients();

		// it is a public email
		if($recipients == null)
			return true;

		// it is not a public email, but no user is logged in
		if($currentUser == null)
			return false;

		// the user is among the recipients
		if(array_search($currentUser->getEmail(), $recipients) !== false)
			return true;

		// the user is admin
		if($currentUser->hasRole("ROLE_ADMIN"))
			return true;

		// any other case
		return false;
	}

	/**
	 * Replace all unmanaged Objects in the array (recursively)
	 * by managed Entities fetched via Doctrine EntityManager.
	 *
	 *  It is assumed that managed objects can be identified
	 *  by their id and implement the function getId() to get that id.
	 *
	 * @param array $vars passed by reference & manipulated but not returned.
	 * @return null
	 */
	private function reAttachAllEntities(array &$vars){
		$em = $this->container->get('doctrine')->getManager();
		foreach ($vars as $key => $next){
			if(is_object($next) && method_exists($next, 'getId')){
				$className = get_class($next);
				$managedEntity = $em->find($className, $next->getId());
				if($managedEntity){
					$vars[$key] = $managedEntity;
				}
				continue;
			} else if (is_array($next)){
				$this->reAttachAllEntities($next);
				continue;
			}
		}

	}

	/**
	 * Serve the image from the templates-folder
	 * @param string $filename
	 * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
	 */
	public function serveImageAction($folderKey, $filename){
		$folder = $this->getTemplateProviderService()->getFolderFrom($folderKey);
		if($folder !== false){
			$fullPath = $folder.$filename;
			$response = BinaryFileResponse::create($fullPath);
			$response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
			$response->headers->set("Content-Type", "image");
			return $response;
		}
		return new FileNotFoundException($filename);
	}

	/**
	 * @return TemplateProviderInterface
	 */
	protected function getTemplateProviderService(){
		return $this->container->get('azine_email_template_provider');
	}

	/**
	 * @param string $view
	 * @param array $parameters
	 * @param Response $response
	 * @return Response
	 */
	protected function renderResponse($view, array $parameters = array(), Response $response = null){
		return $this->container->get('templating')->renderResponse($view, $parameters, $response);
	}

	/**
	 * Get the sent email from the database
	 * @param string $token the token identifying the sent email
	 * @return SentEmail
	 */
	protected function getSentEmailForToken($token){
		$sentEmail = $this->container->get('doctrine')->getRepository('AzineEmailBundle:SentEmail')->findOneByToken($token);
		return $sentEmail;
	}

	/**
	 * Get current user
	 * @throws \LogicException
	 * @return User|null
	 */
	protected function getUser()
	{
		if (!$this->container->has('security.context')) {
			throw new \LogicException('The SecurityBundle is not registered in your application.');
		}

		if (null === $token = $this->container->get('security.context')->getToken()) {
			return null;
		}

		if (!is_object($user = $token->getUser())) {
			return null;
		}

		return $user;
	}

	/**
	 * Send a test-mail for the template to the given email-address
	 * @param strin $template
	 * @param string $email
	 * @return \Symfony\Component\HttpFoundation\RedirectResponse
	 */
	public function sendTestEmailAction($template, $email){
		$locale = $this->container->get('request')->getLocale();

		// get the email-vars for email-sending => absolute fs-paths to images
		$emailVars = $this->container->get('azine_email_web_view_service')->getDummyVarsFor($template, $locale);

		// send the mail
		$message = \Swift_Message::newInstance();
		$sent = $this->getAzineMailer()->sendSingleEmail($email, "Test Recipient", $emailVars, $template.".txt.twig", $this->container->get('request')->getLocale(), "test@examle.com", "Test Mail from AzineEmailBundle", $message);

		$spamReport = $this->getSpamIndexReport($message);
		$spamInfo = "";
		if(is_array($spamReport)){
			if($spamReport['curlHttpCode'] == 200 && $spamReport['success']){
				$spamScore = $spamReport['score'];
				$spamInfo = "SpamScore: $spamScore! \n".$spamReport['report'];
			} else {
				$spamInfo = "Getting the spam-info failed.
							 HttpCode: ".$spamReport['curlHttpCode']."
							 SpamReportMsg: ".$spamReport['message']."
							 cURL-Error: ".$result['curlError'];

			}

			if($spamScore <= 2){
				$this->container->get('session')->getFlashBag()->add('info', $spamInfo);
			} else if($spamScore > 2 && $spamScore < 5){
				$this->container->get('session')->getFlashBag()->add('warn', $spamInfo);
			} else {
				$this->container->get('session')->getFlashBag()->add('error', $spamInfo);
			}
		}

		// inform about sent/failed emails
		if($sent){
			$msg = $this->container->get('translator')->trans('web.pre.view.test.mail.sent.for.%template%.to.%email%', array('%template%' => $template, '%email%' => $email));
			$this->container->get('session')->getFlashBag()->add('info', $msg);
		} else {
			$msg = $this->container->get('translator')->trans('web.pre.view.test.mail.failed.for.%template%.to.%email%', array('%template%' => $template, '%email%' => $email));
			$this->container->get('session')->getFlashBag()->add('warn', $msg);
		}


		// show the index page again.
		return new RedirectResponse($this->container->get('router')->generate('azine_email_template_index', array('customEmail' => $email)));
	}

	/**
	 * Make an RESTful call to http://spamcheck.postmarkapp.com/filter to test the emails-spam-index.
	 * See http://spamcheck.postmarkapp.com/doc
	 * @return array TestResult array('success', 'message', 'curlHttpCode', 'curlError', ['score', 'report'])
	 */
	public function getSpamIndexReport(\Swift_Message $message, $report = 'long'){

		// check if cURL is loaded/available
		if (!function_exists('curl_init')){
			return array(	"success" => false,
							"message" => "No Spam-Check done. cURL module is not available.",
							"curlHttpCode" => "-",
							"curlError" => "-");
		}

		$ch = curl_init("http://spamcheck.postmarkapp.com/filter");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		$data = array("email" => $message->toString(), "options" => $report);
		//$data = array("email" => json_encode(array_filter($raw_email)), "options" => $report);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Accept: application/json"));

		$result = json_decode(curl_exec($ch), true);
		$error = curl_error($ch);
		$result['curlHttpCode'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if(strlen($error) > 0){
			$result['curlError'] = $error;
		}

		if(!array_key_exists("message", $result)){
			$result['message'] = "-";
		}

		return $result;

	}

	/**
	 * @return AzineTwigSwiftMailer
	 */
	private function getAzineMailer(){
		return $this->container->get("azine_email_template_twig_swift_mailer");
	}
}
