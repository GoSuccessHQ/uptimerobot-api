<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Resource\Handwritten;

use GoSuccess\UptimeRobot\Http\FileUpload;
use GoSuccess\UptimeRobot\Http\Method;
use GoSuccess\UptimeRobot\Model\StatusPage;
use GoSuccess\UptimeRobot\Model\StatusPageCreate;
use GoSuccess\UptimeRobot\Model\StatusPageUpdate;
use GoSuccess\UptimeRobot\Resource\StatusPageResource;
use InvalidArgumentException;
use stdClass;

/**
 * Hand-written methods of {@see StatusPageResource}.
 *
 * A status page is sent as JSON, unless a logo or an icon is uploaded with
 * it: then the whole request is multipart/form-data, the only content type
 * the API takes files in. The specification documents how a form carries
 * lists (a repeated `monitorIds[]` field, a single empty one for an empty
 * list). For the design, the API's validator reads nested fields such as
 * `customSettings[page][theme]` as the nested object and checks them like
 * JSON, "true" and "false" included, whereas it rejects a JSON string with
 * "customSettings must be an object" (both verified live with requests it
 * rejected). A form cannot express null or an empty object, so a request
 * with a file and such a value is rejected before it is sent.
 */
trait StatusPageOperations
{
    /**
     * Create a status page.
     *
     * Sent as JSON, or as multipart/form-data when a logo or an icon is
     * uploaded; the specification mentions only the logo for this request,
     * although its model has the icon as well. In the form, the design is
     * sent as nested fields such as customSettings[page][theme], which the
     * API's validator reads like JSON (verified live). A form cannot express
     * null or an empty object, so such a value together with a file raises
     * an InvalidArgumentException before anything is sent: create the page
     * without the file, then upload it with update(). Not verified live
     * beyond the validator: no status page could be created on the test
     * account.
     *
     * `POST /psps`
     *
     * @param StatusPageCreate $page The page; friendlyName is required.
     * @param FileUpload|null  $logo The logo: JPG or PNG, at most 150 KB, 20-400 px wide and
     *                               10-200 px high (per the specification; the API checks it).
     * @param FileUpload|null  $icon The icon, with the same limits.
     *
     * @throws InvalidArgumentException If a file comes with a value that form data cannot
     *                                  express: null or an empty object.
     */
    public function create(StatusPageCreate $page, ?FileUpload $logo = null, ?FileUpload $icon = null): StatusPage
    {
        $data = $this->sendStatusPage(Method::Post, 'psps', $page->toArray(), $logo, $icon);

        return self::toModel(StatusPage::class, $data);
    }

    /**
     * Update a status page.
     *
     * Only the properties that are set are sent, as JSON, or as
     * multipart/form-data when a logo or an icon is uploaded; to replace only
     * the logo, pass an empty StatusPageUpdate. In the form, the design is
     * sent as nested fields such as customSettings[page][theme], which the
     * API's validator reads like JSON (verified live). A form cannot express
     * null or an empty object, so such a value together with a file raises
     * an InvalidArgumentException before anything is sent: make that change
     * and the upload in two calls. The validator runs before the page is
     * looked up, so an invalid change to an unknown ID raises a
     * BadRequestException, a valid one a NotFoundException (both verified
     * live). Not verified live beyond that: the test account has no status
     * pages.
     *
     * `PATCH /psps/{id}`
     *
     * @param int              $id      The status page.
     * @param StatusPageUpdate $changes The properties to change.
     * @param FileUpload|null  $logo    A new logo: JPG or PNG, at most 150 KB, 20-400 px wide and
     *                                  10-200 px high (per the specification; the API checks it).
     * @param FileUpload|null  $icon    A new icon, with the same limits.
     *
     * @throws InvalidArgumentException If a file comes with a value that form data cannot
     *                                  express: null or an empty object.
     */
    public function update(int $id, StatusPageUpdate $changes, ?FileUpload $logo = null, ?FileUpload $icon = null): StatusPage
    {
        $data = $this->sendStatusPage(Method::Patch, "psps/{$id}", $changes->toArray(), $logo, $icon);

        return self::toModel(StatusPage::class, $data);
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws InvalidArgumentException If a file comes with a value that form data cannot express.
     */
    private function sendStatusPage(Method $method, string $path, array $payload, ?FileUpload $logo, ?FileUpload $icon): mixed
    {
        $files = array_filter(['logo' => $logo, 'icon' => $icon], static fn(?FileUpload $file): bool => $file !== null);

        if ($files === []) {
            return $this->connection->json($method, $path, body: $payload);
        }

        return $this->connection->multipart($method, $path, self::formFields($payload), $files);
    }

    /**
     * Flatten nested objects into bracketed field names, e.g.
     * `customSettings[page][theme]`; lists are left to the form encoder,
     * which repeats them as `name[]`.
     *
     * @param array<array-key, mixed> $values
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException On null or an empty object.
     */
    private static function formFields(array $values, string $prefix = '', string $path = ''): array
    {
        $fields = [];

        foreach ($values as $key => $value) {
            $name = $prefix === '' ? (string) $key : "{$prefix}[{$key}]";
            $property = $path === '' ? (string) $key : "{$path}.{$key}";

            if ($value === null) {
                throw new InvalidArgumentException("{$property} is null, which multipart/form-data cannot express; send it without the logo and icon, which a separate update() can upload.");
            }

            if ($value instanceof stdClass) {
                throw new InvalidArgumentException("{$property} is an empty object, which multipart/form-data cannot express; send it without the logo and icon, which a separate update() can upload.");
            }

            if (\is_array($value) && !array_is_list($value)) {
                $fields = [...$fields, ...self::formFields($value, $name, $property)];

                continue;
            }

            $fields[$name] = $value;
        }

        return $fields;
    }
}
