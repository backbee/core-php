<?php

namespace BackBeeCloud\Api\Controller;

use BackBee\BBApplication;
use BackBee\Security\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class UserController extends AbstractController
{
    /**
     * @var Doctrine\ORM\EntityManager
     */
    protected $entyMgr;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var BackBee\Security\SecurityContext
     */
    protected $securityContext;

    public function __construct(BBApplication $app)
    {
        parent::__construct($app);

        $this->entyMgr = $app->getEntityManager();
        $this->request = $app->getRequest();
        $this->securityContext = $app->getSecurityContext();
    }

    public function updatePassword($id)
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        $user = $this->securityContext->getToken()->getUser();
        if ((int) $id !== $user->getId()) {
            return new JsonResponse([
                'error'  => 'forbidden',
                'reason' => sprintf("You're not allow to update password of user #%d", $id),
            ], Response::HTTP_FORBIDDEN);
        }

        $password = (string) $this->request->request->get('password');
        $newPassword = (string) $this->request->request->get('new_password');
        $confirmPassword = (string) $this->request->request->get('confirm_new_password');
        if (false == $password || false == $newPassword || false == $confirmPassword) {
            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => "'password', 'new_password' and 'confirm_new_password' parameters are required",
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entyMgr->refresh($user);
        $encoder = $this->securityContext->getEncoderFactory()->getEncoder(User::class);
        $encodedPassword = $encoder->encodePassword($password, '');
        if ($encodedPassword !== $user->getPassword()) {
            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => "Provided password is not valid",
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->validatePassword($newPassword)) {
            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => sprintf(
                    'New password must contain at least %s, %s, %s, %s and %s',
                    '8 characters',
                    'one capital letter',
                    'one lowercase letter',
                    'one digit',
                    'one symbol'
                ),
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($newPassword !== $confirmPassword) {
            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => 'New password and confirm password must be equals',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($encoder->encodePassword($newPassword, ''));
        $this->entyMgr->flush($user);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Expected password pattern:
     *
     * - at least 8 characters
     * - at least one capital letter
     * - at least one lowercase letter
     * - at least one digit
     * - at least one symbol
     *
     * @param  string $password
     * @return bool
     */
    protected function validatePassword($password)
    {
        return
            8 <= strlen($password)
            && 1 === preg_match('/[A-Z]+/', $password)
            && 1 === preg_match('/[a-z]+/', $password)
            && 1 === preg_match('/[0-9]+/', $password)
            && 1 === preg_match('/[`~\!@#\$%\^\&\*\(\)\-_\=\+\[\{\}\]\\\|;:\'",<.>\/\?]+/', $password)
        ;
    }
}
