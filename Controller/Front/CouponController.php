<?php


namespace OpenApi\Controller\Front;


use OpenApi\Model\Api\Coupon;
use OpenApi\Model\Api\Error;
use OpenApi\OpenApi;
use Thelia\Core\Event\Coupon\CouponConsumeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\HttpFoundation\Request;
use OpenApi\Annotations as OA;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CouponQuery;

/**
 * @Route("/coupon", name="coupon")
 */
class CouponController extends BaseFrontOpenApiController
{
    /**
     * @Route("", name="submit_coupon", methods="POST")
     *
     * @OA\Post(
     *     path="/coupon",
     *     tags={"coupon"},
     *     summary="Submit a coupon",
     *
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="code",
     *                     type="string"
     *                 ),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *          response="200",
     *          description="Success",
     *          @OA\JsonContent(ref="#/components/schemas/Coupon")
     *     ),
     *     @OA\Response(
     *          response="400",
     *          description="Bad request",
     *          @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function submitCoupon(Request $request)
    {
        try {
            $cart = $request->getSession()->getSessionCart();
            if (null === $cart) {
                throw new \Exception(Translator::getInstance()->trans('No cart found', [], OpenApi::DOMAIN_NAME));
            }

            $openApiCoupon = (new Coupon())->createFromJson($request->getContent());

            if (null === $openApiCoupon->getCode()) {
                throw new \Exception(Translator::getInstance()->trans('Coupon code cannot be null', [], OpenApi::DOMAIN_NAME));
            }

            $theliaCoupon = CouponQuery::create()->filterByCode($openApiCoupon->getCode())->findOne();

            if (null === $theliaCoupon) {
                throw new \Exception(Translator::getInstance()->trans('No coupons were found for this coupon code.', [], OpenApi::DOMAIN_NAME));
            }

            $event = new CouponConsumeEvent($openApiCoupon->getCode());
            $this->getDispatcher()->dispatch(TheliaEvents::COUPON_CONSUME, $event);
            $openApiCoupon->createFromTheliaCoupon($theliaCoupon);

            return new JsonResponse($openApiCoupon, 200);
        } catch (\Exception $exception) {
            return new JsonResponse(
                new Error(
                    Translator::getInstance()->trans('Error while trying to submit coupon', [], OpenApi::DOMAIN_NAME),
                    $exception->getMessage()
                ),
                400
            );
        }
    }
}