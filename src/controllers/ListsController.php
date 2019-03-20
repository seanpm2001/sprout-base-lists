<?php

namespace barrelstrength\sproutbaselists\controllers;

use barrelstrength\sproutbaselists\base\ListType;
use barrelstrength\sproutbaselists\elements\SubscriberList;
use barrelstrength\sproutbaselists\listtypes\SubscriberListType;
use barrelstrength\sproutbaselists\models\Subscription;
use barrelstrength\sproutbaselists\SproutBaseLists;
use craft\web\Controller;
use Craft;
use yii\web\Response;

class ListsController extends Controller
{
    /**
     * Allow users who are not logged in to subscribe and unsubscribe from lists
     *
     * @var array
     */
    protected $allowAnonymous = ['actionSubscribe', 'actionUnsubscribe'];

    /**
     * Prepare variables for the List Edit Template
     *
     * @param null $type
     * @param null $listId
     * @param null $list
     *
     * @return Response
     * @throws \Exception
     */
    public function actionEditListTemplate($type = null, $listId = null, $list = null): Response
    {
        $type = $type ?? SubscriberListType::class;

        $listType = SproutBaseLists::$app->lists->getListType($type);

        if ($list == null) {
            $list = new SubscriberList();
        }

        $continueEditingUrl = null;

        if ($listId != null) {

            /**
             * @var $listType ListType
             */
            $list = $listType->getListById($listId);

            $continueEditingUrl = 'sprout-lists/lists/edit/'.$listId;
        }

        return $this->renderTemplate('sprout-base-lists/lists/_edit', [
            'listId' => $listId,
            'list' => $list,
            'continueEditingUrl' => $continueEditingUrl
        ]);
    }

    /**
     * Saves a list
     *
     * @return null
     * @throws \Exception
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSaveList()
    {
        $this->requirePostRequest();

        $listId = Craft::$app->request->getBodyParam('listId');

        $list = new SubscriberList();

        if ($listId != null) {
            $list = Craft::$app->getElements()->getElementById($listId);
        }

        $list->name = Craft::$app->request->getBodyParam('name');
        $list->handle = Craft::$app->request->getBodyParam('handle');
        $list->type = Craft::$app->request->getRequiredBodyParam('type');

        /**
         * @var $listType ListType
         */
        $listType = SproutBaseLists::$app->lists->getListType($list->type);
        $list->type = get_class($listType);
        $session = Craft::$app->getSession();

        if ($session && $listType->saveList($list)) {
            $session->setNotice(Craft::t('sprout-lists', 'List saved.'));

            return $this->redirectToPostedUrl();
        }

        $session->setError(Craft::t('sprout-lists', 'Unable to save list.'));

        Craft::$app->getUrlManager()->setRouteParams([
            'list' => $list
        ]);

        return null;
    }

    /**
     * Deletes a list
     *
     * @return Response
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionDeleteList(): Response
    {
        $this->requirePostRequest();

        $listId = Craft::$app->getRequest()->getBodyParam('listId');
        $session = Craft::$app->getSession();

        if ($session && SproutBaseLists::$app->lists->deleteList($listId)) {
            if (Craft::$app->getRequest()->getIsAjax()) {
                return $this->asJson([
                    'success' => true
                ]);
            }

            $session->setNotice(Craft::t('sprout-lists', 'List deleted.'));

            return $this->redirectToPostedUrl();
        }

        if (Craft::$app->getRequest()->getIsAjax()) {
            return $this->asJson([
                'success' => false
            ]);
        }

        $session->setError(Craft::t('sprout-lists', 'Unable to delete list.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Adds a subscriber to a list
     *
     * @return Response | null
     * @throws \Throwable
     * @throws \yii\base\Exception
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSubscribe()
    {
        $this->requirePostRequest();

        $subscription = new Subscription();
        $subscription->listHandle = Craft::$app->getRequest()->getRequiredBodyParam('listHandle');
        $subscription->listId = Craft::$app->getRequest()->getBodyParam('listId');
        $subscription->userId = Craft::$app->getRequest()->getBodyParam('userId');
        $subscription->email = Craft::$app->getRequest()->getBodyParam('email');
        $subscription->elementId = Craft::$app->getRequest()->getBodyParam('elementId');
        $subscription->firstName = Craft::$app->getRequest()->getBodyParam('firstName');
        $subscription->lastName = Craft::$app->getRequest()->getBodyParam('lastName');

        $listType = SproutBaseLists::$app->lists->getListTypeByHandle($subscription->listHandle);

        $subscription->listType = get_class($listType);

        $email = trim($subscription->email);

        if (!empty($email) && filter_var($subscription->email, FILTER_VALIDATE_EMAIL) === false) {
            $subscription->addError('invalid-email',
                Craft::t('sprout-lists', 'Submitted email is invalid.'));
        }

        if ($listType->subscribe($subscription)) {
            if (Craft::$app->getRequest()->getIsAjax()) {
                return $this->asJson([
                    'success' => true,
                ]);
            }

            return $this->redirectToPostedUrl();
        }

        return Craft::$app->getUrlManager()->setRouteParams([
            'subscription' => $subscription
        ]);
    }

    /**
     * Removes a subscriber from a list
     *
     * @return Response
     * @throws \Exception
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionUnsubscribe(): Response
    {
        $this->requirePostRequest();

        $subscription = new Subscription();
        $subscription->listHandle = Craft::$app->getRequest()->getBodyParam('listHandle');
        $subscription->listId = Craft::$app->getRequest()->getBodyParam('listId');
        $subscription->userId = Craft::$app->getRequest()->getBodyParam('userId');
        $subscription->email = Craft::$app->getRequest()->getBodyParam('email');
        $subscription->elementId = Craft::$app->getRequest()->getBodyParam('elementId');

        $listType = SproutBaseLists::$app->lists->getListTypeByHandle($subscription->listHandle);

        $subscription->listType = get_class($listType);

        if ($listType->unsubscribe($subscription)) {
            if (Craft::$app->getRequest()->getIsAjax()) {
                return $this->asJson([
                    'success' => true,
                ]);
            }

            return $this->redirectToPostedUrl();
        }

        $errors = [Craft::t('sprout-lists', 'Unable to remove subscription.')];

        if (Craft::$app->getRequest()->getIsAjax()) {
            return $this->asJson([
                'errors' => $errors,
            ]);
        }

        Craft::$app->getUrlManager()->setRouteParams([
            'errors' => $errors
        ]);

        return $this->redirectToPostedUrl();
    }
}