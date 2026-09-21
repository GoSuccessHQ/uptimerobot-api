<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Tools\Generator;

use GoSuccess\UptimeRobot\Tests\Support\Generator\GeneratorFixture;
use GoSuccess\UptimeRobot\Tools\Generator\Analysis;
use GoSuccess\UptimeRobot\Tools\Generator\Config\ApiConfig;
use GoSuccess\UptimeRobot\Tools\Generator\Generator;
use GoSuccess\UptimeRobot\Tools\Generator\PhpType;
use GoSuccess\UptimeRobot\Tools\Generator\Registry;
use GoSuccess\UptimeRobot\Tools\Generator\ResourceBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * The generator derives only what is safe and fails loudly, with every
 * location the configuration has to decide on, instead of guessing.
 */
#[CoversClass(Generator::class)]
#[CoversClass(Registry::class)]
#[CoversClass(ResourceBuilder::class)]
#[CoversClass(ApiConfig::class)]
final class ConfigurationChecksTest extends TestCase
{
    public function testListsEveryInlineEnumWithoutAName(): void
    {
        $this->assertProblems(
            fn(): Analysis => $this->analyze(['ThingDto' => self::object([
                'status' => ['type' => 'string', 'enum' => ['UP', 'DOWN']],
                'regions' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['na', 'eu']]],
            ])]),
            "Inline enums without a name; name them in 'enums' (or keep the plain type with false):\n    ThingDto.status\n    ThingDto.regions[]",
        );
    }

    public function testNamesInlineEnumsAndSharesIdenticalOnes(): void
    {
        $analysis = $this->analyze(
            [
                'ThingDto' => self::object([
                    'status' => ['type' => 'string', 'enum' => ['UP', 'LOOKS_DOWN']],
                    'previous' => ['type' => 'string', 'enum' => ['UP', 'LOOKS_DOWN'], 'x-enumNames' => ['UP', 'LOOKS_DOWN']],
                    'plain' => ['type' => 'string', 'enum' => ['a', 'b']],
                ]),
            ],
            ['enums' => ['ThingDto.status' => 'ThingStatus', 'ThingDto.previous' => 'ThingStatus', 'ThingDto.plain' => false]],
        );

        $enum = $analysis->registry->enums['GoSuccess\\UptimeRobot\\Tests\\Fixture\\Unused\\Enum\\ThingStatus'];
        self::assertSame('string:Up=UP,LooksDown=LOOKS_DOWN', $enum->signature());
        self::assertSame(['ThingDto.status', 'ThingDto.previous'], $enum->schemas);
        self::assertCount(1, $analysis->registry->enums);
        self::assertSame(PhpType::STRING, $this->propertyType($analysis, 'ThingDto', 'plain')->kind);
    }

    public function testRejectsSharingANameBetweenDifferentEnums(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("ThingDto.other and ThingDto.status both map to GoSuccess\\UptimeRobot\\Tests\\Fixture\\Unused\\Enum\\ThingStatus but differ:\n    string:Up=UP\n    string:Up=UP,Down=DOWN");

        $this->analyze(
            ['ThingDto' => self::object(['status' => ['type' => 'string', 'enum' => ['UP', 'DOWN']], 'other' => ['type' => 'string', 'enum' => ['UP']]])],
            ['enums' => ['ThingDto.*' => 'ThingStatus']],
        );
    }

    public function testRequiresNamesForTheCasesOfIntegerEnums(): void
    {
        $spec = ['ThingDto' => self::object(['caseType' => ['type' => 'number', 'enum' => [0, 1]]])];

        try {
            $this->analyze($spec, ['enums' => ['ThingDto.caseType' => 'KeywordCaseType']]);
            self::fail('Expected an exception.');
        } catch (RuntimeException $e) {
            self::assertSame('ThingDto.caseType: no name for value 0. Name the cases in enumCases.', $e->getMessage());
        }

        $analysis = $this->analyze($spec, [
            'enums' => ['ThingDto.caseType' => 'KeywordCaseType'],
            'enumCases' => ['KeywordCaseType' => [0 => 'CaseSensitive', 1 => 'CaseInsensitive']],
        ]);

        self::assertSame('int:CaseSensitive=0,CaseInsensitive=1', array_values($analysis->registry->enums)[0]->signature());
    }

    public function testListsEveryUnclassifiedNumber(): void
    {
        $paths = ['/things/{id}' => ['get' => [
            'operationId' => 'ThingsController_get',
            'parameters' => [
                ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'number']],
                ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'number']],
            ],
            'responses' => ['200' => self::json(self::ref('ThingDto'))],
        ]]];

        $this->assertProblems(
            fn(): Analysis => $this->analyze(
                ['ThingDto' => self::object([
                    'count' => ['type' => 'number'],
                    'exact' => ['type' => 'integer'],
                    'ids' => ['type' => 'array', 'items' => ['type' => 'number']],
                    'thresholds' => ['type' => 'object', 'additionalProperties' => ['type' => 'number']],
                ])],
                paths: $paths,
            ),
            "Numbers that are neither 'integers' nor 'floats':\n    ThingsController_get.id\n    ThingsController_get.limit\n    ThingDto.count\n    ThingDto.ids[]\n    ThingDto.thresholds{}",
        );
    }

    public function testClassifiesNumbers(): void
    {
        $analysis = $this->analyze(
            ['ThingDto' => self::object(['count' => ['type' => 'number'], 'ratio' => ['type' => 'number'], 'ids' => ['type' => 'array', 'items' => ['type' => 'number']]])],
            ['integers' => ['ThingDto.count', '*.ids[]'], 'floats' => ['ThingDto.ratio']],
        );

        self::assertSame(PhpType::INT, $this->propertyType($analysis, 'ThingDto', 'count')->kind);
        self::assertSame(PhpType::FLOAT, $this->propertyType($analysis, 'ThingDto', 'ratio')->kind);
        self::assertSame(PhpType::INT, $this->propertyType($analysis, 'ThingDto', 'ids')->itemOrFail()->kind);
    }

    public function testRejectsANumberThatIsBothIntegerAndFloat(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("ThingDto.count is listed in both 'integers' and 'floats'.");

        $this->analyze(['ThingDto' => self::object(['count' => ['type' => 'number']])], ['integers' => ['*.count'], 'floats' => ['ThingDto.*']]);
    }

    public function testListsLocationsWithoutAType(): void
    {
        $this->assertProblems(
            fn(): Analysis => $this->analyze(['ThingDto' => self::object([
                'status' => [],
                'createdAt' => ['oneOf' => [[], ['type' => 'string']]],
                'meta' => ['nullable' => true],
                'anything' => ['type' => 'array'],
            ])]),
            "Locations without a type; give them one in 'types' or accept any JSON value in 'mixed':\n    ThingDto.status\n    ThingDto.createdAt\n    ThingDto.meta\n    ThingDto.anything[]",
        );
    }

    public function testTypesReplaceTheSchemaOfALocationAndKeepItsDescription(): void
    {
        $analysis = $this->analyze(
            ['ThingDto' => self::object([
                'status' => ['description' => 'Erased by zod.'],
                'createdAt' => ['oneOf' => [[], ['type' => 'string']]],
                'meta' => [],
            ])],
            [
                'types' => [
                    'ThingDto.status' => ['type' => 'string'],
                    'ThingDto.createdAt' => ['type' => 'string', 'format' => 'date-time'],
                ],
                'mixed' => ['ThingDto.meta'],
            ],
        );

        $model = array_values($analysis->registry->models)[0];
        self::assertSame(PhpType::STRING, $model->properties[0]->type->kind);
        self::assertSame('Erased by zod.', $model->properties[0]->description);
        self::assertSame(PhpType::DATE, $model->properties[1]->type->kind);
        self::assertSame(PhpType::MIXED, $model->properties[2]->type->kind);
    }

    public function testTypesKeepTheNullabilityAndReadOnlyOfTheLocationTheyReplace(): void
    {
        $analysis = $this->analyze(
            ['ThingDto' => self::object([
                // Erased by zod, like MonitorDto.httpMethodType.
                'method' => ['nullable' => true],
                'count' => ['allOf' => [self::ref('CountDto')], 'nullable' => true],
                'label' => ['nullable' => true],
                'key' => ['readOnly' => true],
            ]), 'CountDto' => ['description' => 'Erased as well.']],
            ['types' => [
                'ThingDto.method' => ['type' => 'string'],
                'ThingDto.count' => ['type' => 'integer'],
                // Stated by the entry, so it wins.
                'ThingDto.label' => ['type' => 'string', 'nullable' => false],
                'ThingDto.key' => ['type' => 'string'],
            ]],
        );

        $model = array_values($analysis->registry->models)[0];
        self::assertSame(['method', 'count', 'label', 'key'], array_map(static fn($property): string => $property->jsonName, $model->properties));
        self::assertSame(PhpType::STRING, $model->properties[0]->type->kind);
        self::assertTrue($model->properties[0]->nullable);
        self::assertSame(PhpType::INT, $model->properties[1]->type->kind);
        self::assertTrue($model->properties[1]->nullable);
        self::assertFalse($model->properties[2]->nullable);
        self::assertTrue($model->properties[3]->readOnly);
    }

    public function testRejectsATypeTheSpecificationAlreadyHas(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('types: the specification documents ThingDto.name like this now; remove the entry.');

        $this->analyze(
            ['ThingDto' => self::object(['name' => ['type' => 'string', 'description' => 'The name.']])],
            ['types' => ['ThingDto.name' => ['type' => 'string']]],
        );
    }

    public function testListsEntriesThatMatchNothing(): void
    {
        $this->assertProblems(
            fn(): Analysis => $this->analyze(['ThingDto' => self::object(['name' => ['type' => 'string']])], [
                'enums' => ['ThingDto.status' => 'ThingStatus'],
                'types' => ['ThingDto.missing' => ['type' => 'string']],
                'integers' => ['*.id'],
                'floats' => ['ThingDto.ratio'],
                'mixed' => ['ThingDto.meta'],
                'excludedProperties' => ['ThingDto.logo'],
                'nullableProperties' => ['ThingDto.url'],
                'commaSeparated' => ['ThingsController_get.status'],
                'schemas' => ['ThingDto.owner' => 'Owner'],
                'properties' => ['ThingDto.IP' => 'ip'],
                'enumCases' => ['Region' => ['na' => 'NorthAmerica']],
            ]),
            "'enums' entries that match nothing the configured operations use:\n    ThingDto.status",
            "'types' entries that match nothing the configured operations use:\n    ThingDto.missing",
            "'integers' entries that match nothing the configured operations use:\n    *.id",
            "'floats' entries that match nothing the configured operations use:\n    ThingDto.ratio",
            "'mixed' entries that match nothing the configured operations use:\n    ThingDto.meta",
            "'excludedProperties' entries that match nothing the configured operations use:\n    ThingDto.logo",
            "'nullableProperties' entries that match nothing the configured operations use:\n    ThingDto.url",
            "'commaSeparated' entries that match nothing the configured operations use:\n    ThingsController_get.status",
            "Configured names that the configured operations do not use:\n    schemas: ThingDto.owner\n    properties: ThingDto.IP\n    enumCases: Region",
        );
    }

    public function testAddsWhatTheSpecificationLacksUntilItDocumentsIt(): void
    {
        $schemas = ['ThingDto' => self::object(['name' => ['type' => 'string']])];
        $analysis = $this->analyze($schemas, ['additions' => ['properties' => ['ThingDto.apiKey' => ['type' => 'string']]]]);

        self::assertSame(['name', 'apiKey'], array_map(static fn($property): string => $property->jsonName, array_values($analysis->registry->models)[0]->properties));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fixture documents ThingDto.name now; remove the addition.');

        $this->analyze($schemas, ['additions' => ['properties' => ['ThingDto.name' => ['type' => 'string']]]]);
    }

    public function testRejectsUnknownSchemasInTheConfiguration(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown schemas or operations in the configuration: TingDto.');

        $this->analyze(['ThingDto' => self::object(['name' => ['type' => 'string']])], ['schemas' => ['TingDto' => 'Thing']]);
    }

    public function testRejectsFileUploadsInJsonModels(): void
    {
        $paths = ['/things' => ['post' => [
            'operationId' => 'ThingsController_create',
            'requestBody' => ['required' => true, 'content' => [
                'application/json' => ['schema' => self::ref('CreateThingDto')],
                'multipart/form-data' => ['schema' => self::ref('CreateThingDto')],
            ]],
            'responses' => ['201' => ['description' => '']],
        ]]];
        $schemas = ['CreateThingDto' => self::object(['name' => ['type' => 'string'], 'logo' => ['type' => 'string', 'format' => 'binary']])];
        $resources = ['resources' => ['things' => ['class' => 'ThingResource', 'description' => 'Things.', 'methods' => ['create' => ['operation' => 'ThingsController_create']]]]];

        try {
            $this->analyze($schemas, $resources, $paths);
            self::fail('Expected an exception.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('CreateThingDto.logo is a file upload (format: binary), which a JSON model cannot carry.', $e->getMessage());
        }

        $analysis = $this->analyze($schemas, [...$resources, 'excludedProperties' => ['CreateThingDto.logo']], $paths);
        $model = array_values($analysis->registry->models)[0];
        self::assertSame(['name'], array_map(static fn($property): string => $property->jsonName, $model->properties));
    }

    public function testRequiresHandWrittenMethodsForBodiesThatAreNotJson(): void
    {
        $paths = ['/logos' => ['post' => [
            'operationId' => 'LogosController_upload',
            'requestBody' => ['required' => true, 'content' => ['multipart/form-data' => ['schema' => self::object(['file' => ['type' => 'string', 'format' => 'binary']])]]],
            'responses' => ['201' => ['description' => '']],
        ]]];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LogosController_upload (upload): request body multipart/form-data needs a hand-written method.');

        $this->analyze([], ['resources' => ['logos' => ['class' => 'LogoResource', 'description' => 'Logos.', 'methods' => ['upload' => ['operation' => 'LogosController_upload']]]]], $paths);
    }

    public function testSendsAnEmptyBodyOnlyWhereTheSpecificationDeclaresNone(): void
    {
        $paths = ['/things/{id}/pin' => ['post' => [
            'operationId' => 'ThingsController_pin',
            'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'number']]],
            'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => self::object(['note' => ['type' => 'string']])]]],
            'responses' => ['200' => ['description' => '']],
        ]]];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("ThingsController_pin (pin): 'body' => 'empty' is for operations without a request body, but this one declares one.");

        $this->analyze([], [
            'integers' => ['ThingsController_pin.id'],
            'resources' => ['things' => ['class' => 'ThingResource', 'description' => 'Things.', 'methods' => ['pin' => ['operation' => 'ThingsController_pin', 'body' => 'empty']]]],
        ], $paths);
    }

    public function testChecksThatEveryOperationIsCoveredExactlyOnce(): void
    {
        $paths = [
            '/things' => ['get' => ['operationId' => 'ThingsController_get', 'responses' => ['200' => self::json(self::ref('ThingDto'))]]],
            '/things/{id}' => ['delete' => ['operationId' => 'ThingsController_delete', 'parameters' => [['name' => 'id', 'in' => 'path', 'schema' => ['type' => 'string']]], 'responses' => ['204' => ['description' => '']]]],
            '/others' => ['get' => ['operationId' => 'OthersController_list', 'responses' => ['204' => ['description' => '']]]],
        ];

        $this->assertProblems(
            fn(): Analysis => $this->analyze(['ThingDto' => self::object(['name' => ['type' => 'string']])], ['ignored' => [
                'ThingsController_get' => 'Implemented after all.',
                'ThingsController_gone' => 'Removed from the specification.',
                'OthersController_list' => ' ',
            ]], $paths),
            "Coverage check failed:\n    implemented but also ignored: ThingsController_get\n    not implemented: ThingsController_delete\n    ignored operation does not exist: ThingsController_gone\n    ignored without a reason: OthersController_list",
        );
    }

    public function testRejectsAnOperationThatIsImplementedTwice(): void
    {
        $this->assertProblems(
            fn(): Analysis => $this->analyze(['ThingDto' => self::object(['name' => ['type' => 'string']])], ['resources' => [
                'things' => ['class' => 'ThingResource', 'description' => 'Things.', 'methods' => [
                    'get' => ['operation' => 'ThingsController_get'],
                    'fetch' => ['operation' => 'ThingsController_get'],
                ]],
            ]]),
            'implemented twice: ThingsController_get (ThingResource::get and ThingResource::fetch)',
        );
    }

    public function testRejectsAutomaticClassNamesThatCollide(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ThingDto.owner and ThingOwnerDto both map to GoSuccess\\UptimeRobot\\Tests\\Fixture\\Unused\\Model\\ThingOwner.');

        $this->analyze([
            'ThingDto' => self::object(['main' => self::ref('ThingOwnerDto'), 'owner' => self::object(['id' => ['type' => 'string'], 'extra' => ['type' => 'string']])]),
            'ThingOwnerDto' => self::object(['id' => ['type' => 'string']]),
        ]);
    }

    public function testRejectsUnknownConfigurationKeys(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fixture.php: unknown key(s) integer.');

        $this->analyze(['ThingDto' => self::object(['name' => ['type' => 'string']])], ['integer' => ['*.id']]);
    }

    public function testRequiresACursorParameterForPagination(): void
    {
        $paths = ['/things' => ['get' => ['operationId' => 'ThingsController_list', 'responses' => ['200' => self::json(self::object(['data' => ['type' => 'array', 'items' => self::ref('ThingDto')]]))]]]];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ThingsController_list (list): there is no query parameter cursor to request further pages with.');

        $this->analyze(['ThingDto' => self::object(['name' => ['type' => 'string']])], [
            'pagination' => ['nextLink' => ['cursor' => 'cursor', 'items' => 'data', 'next' => 'nextLink', 'factory' => 'Pagination\\Cursor::fromNextLink']],
            'resources' => ['things' => ['class' => 'ThingResource', 'description' => 'Things.', 'methods' => ['list' => ['operation' => 'ThingsController_list', 'pagination' => 'nextLink', 'all' => 'all']]]],
        ], $paths);
    }

    public function testRequiresTheFieldThePaginationReadsTheNextPageFrom(): void
    {
        // Paginated like GET /tags, but configured with the style of the other lists.
        $paths = ['/things' => ['get' => [
            'operationId' => 'ThingsController_list',
            'parameters' => [['name' => 'cursor', 'in' => 'query', 'schema' => ['type' => 'string']]],
            'responses' => ['200' => self::json(self::object(['data' => ['type' => 'array', 'items' => self::ref('ThingDto')], 'nextCursorId' => ['type' => 'string']]))],
        ]]];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ThingsController_list (list): response has no nextLink property, from which the nextLink pagination reads the next page.');

        $this->analyze(['ThingDto' => self::object(['name' => ['type' => 'string']])], [
            'pagination' => ['nextLink' => ['cursor' => 'cursor', 'items' => 'data', 'next' => 'nextLink', 'factory' => 'Pagination\\Cursor::fromNextLink']],
            'resources' => ['things' => ['class' => 'ThingResource', 'description' => 'Things.', 'methods' => ['list' => ['operation' => 'ThingsController_list', 'pagination' => 'nextLink', 'all' => 'all']]]],
        ], $paths);
    }

    public function testRejectsResourcesThatShareAClass(): void
    {
        $paths = [
            '/things' => ['get' => ['operationId' => 'ThingsController_get', 'responses' => ['200' => self::json(self::ref('ThingDto'))]]],
            '/others' => ['delete' => ['operationId' => 'OthersController_delete', 'responses' => ['204' => ['description' => '']]]],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('resources.things and resources.others both use the class thingResource.');

        // One class file would overwrite the other; PHP class names ignore case.
        $this->analyze(['ThingDto' => self::object(['name' => ['type' => 'string']])], ['resources' => [
            'things' => ['class' => 'ThingResource', 'description' => 'Things.', 'methods' => ['get' => ['operation' => 'ThingsController_get']]],
            'others' => ['class' => 'thingResource', 'description' => 'Others.', 'methods' => ['delete' => ['operation' => 'OthersController_delete']]],
        ]], $paths);
    }

    public function testRejectsHiddenParametersThatMatchNothing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ThingsController_get (get): cursorr is hidden, but is neither a query nor a header parameter.');

        $this->analyze(
            ['ThingDto' => self::object(['name' => ['type' => 'string']])],
            ['resources' => ['things' => ['class' => 'ThingResource', 'description' => 'Things.', 'methods' => ['get' => ['operation' => 'ThingsController_get', 'hidden' => ['cursorr']]]]]],
            self::withQuery(['name' => 'cursor', 'in' => 'query', 'schema' => ['type' => 'string']]),
        );
    }

    public function testRejectsParameterNamesThatMatchNothing(): void
    {
        $paths = ['/things/{id}' => ['delete' => [
            'operationId' => 'ThingsController_delete',
            'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
            'responses' => ['204' => ['description' => '']],
        ]]];
        $resource = static fn(array $parameters): array => ['resources' => ['things' => [
            'class' => 'ThingResource',
            'description' => 'Things.',
            // Names the resource shares with all its methods need not occur in each.
            'parameters' => ['monitorId' => 'monitor'],
            'methods' => ['delete' => ['operation' => 'ThingsController_delete', 'parameters' => $parameters]],
        ]]];

        $analysis = $this->analyze([], $resource(['id' => 'thingId']), $paths);
        self::assertSame('thingId', $analysis->resources[0]->methods[0]->parameters[0]->phpName);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("ThingsController_delete (delete): 'parameters' renames tagId, which is neither a path or query parameter nor the body or one of its flattened properties.");

        $this->analyze([], $resource(['tagId' => 'tag']), $paths);
    }

    public function testCommaSeparatedParametersMustBeLists(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ThingsController_get (get): the comma-separated query parameter status must be a list of strings, integers or enums; give it an array type in \'types\'.');

        $this->analyze(['ThingDto' => self::object(['name' => ['type' => 'string']])], ['commaSeparated' => ['ThingsController_get.status']], self::withQuery(['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']]));
    }

    public function testRejectsACommaSeparatedEntryTheSpecificationDocuments(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('commaSeparated: the specification documents ThingsController_get.status as comma-separated now; remove the entry.');

        $this->analyze(
            ['ThingDto' => self::object(['name' => ['type' => 'string']])],
            ['commaSeparated' => ['ThingsController_get.status']],
            self::withQuery(['name' => 'status', 'in' => 'query', 'explode' => false, 'schema' => ['type' => 'array', 'items' => ['type' => 'string']]]),
        );
    }

    public function testReadsCommaSeparatedParametersFromTheSpecification(): void
    {
        $analysis = $this->analyze(
            ['ThingDto' => self::object(['name' => ['type' => 'string']])],
            [],
            self::withQuery(['name' => 'status', 'in' => 'query', 'style' => 'form', 'explode' => false, 'schema' => ['type' => 'array', 'items' => ['type' => 'string']]]),
        );

        self::assertTrue($analysis->resources[0]->methods[0]->parameters[0]->commaSeparated);
    }

    /**
     * @param array<string, mixed>      $schemas
     * @param array<string, mixed>      $config
     * @param array<string, mixed>|null $paths   Defaults to GET /things returning ThingDto.
     */
    private function analyze(array $schemas, array $config = [], ?array $paths = null): Analysis
    {
        $paths ??= ['/things' => ['get' => ['operationId' => 'ThingsController_get', 'responses' => ['200' => self::json(self::ref('ThingDto'))]]]];

        return GeneratorFixture::analyze(
            ['paths' => $paths, 'components' => ['schemas' => $schemas]],
            [
                'naming' => ['stripSuffixes' => ['Dto']],
                'resources' => ['things' => ['class' => 'ThingResource', 'description' => 'Things.', 'methods' => ['get' => ['operation' => 'ThingsController_get']]]],
                ...$config,
            ],
        );
    }

    /**
     * @param callable(): mixed $analyze
     */
    private function assertProblems(callable $analyze, string ...$expected): void
    {
        try {
            $analyze();
        } catch (Throwable $e) {
            self::assertInstanceOf(RuntimeException::class, $e);
            self::assertStringStartsWith('The configuration does not match the specification:', $e->getMessage());

            foreach ($expected as $fragment) {
                self::assertStringContainsString($fragment, $e->getMessage());
            }

            return;
        }

        self::fail('Expected the analysis to fail.');
    }

    private function propertyType(Analysis $analysis, string $source, string $json): PhpType
    {
        foreach ($analysis->registry->models as $model) {
            if ($model->source !== $source) {
                continue;
            }

            foreach ($model->properties as $property) {
                if ($property->jsonName === $json) {
                    return $property->type;
                }
            }
        }

        self::fail("{$source}.{$json} was not registered.");
    }

    /**
     * @param array<string, mixed> $parameter
     *
     * @return array<string, mixed>
     */
    private static function withQuery(array $parameter): array
    {
        return ['/things' => ['get' => ['operationId' => 'ThingsController_get', 'parameters' => [$parameter], 'responses' => ['200' => self::json(self::ref('ThingDto'))]]]];
    }

    /**
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    private static function object(array $properties): array
    {
        return ['type' => 'object', 'properties' => $properties];
    }

    /**
     * @return array{'$ref': string}
     */
    private static function ref(string $name): array
    {
        return ['$ref' => "#/components/schemas/{$name}"];
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private static function json(array $schema): array
    {
        return ['description' => '', 'content' => ['application/json' => ['schema' => $schema]]];
    }
}
