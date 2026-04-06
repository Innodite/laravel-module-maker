├── README.md
├── composer.json
├── composer.lock
├── config
│   └── make-module.php
├── docs
│   ├── assets
│   ├── index.html
│   └── test-reports
│       └── README.md
├── phpunit.xml
├── scripts
│   └── validate-stubs.sh
├── skills
│   └── module-maker.md
├── src
│   ├── Commands
│   │   ├── CheckEnvCommand.php
│   │   ├── MakeModuleCommand.php
│   │   ├── MigrateModulesCommand.php
│   │   ├── MigrateOneCommand.php
│   │   ├── MigratePlanCommand.php
│   │   ├── MigrationSyncCommand.php
│   │   ├── ModuleCheckCommand.php
│   │   ├── PublishFrontendCommand.php
│   │   ├── SeedOneCommand.php
│   │   ├── SetupModuleMakerCommand.php
│   │   ├── TestModuleCommand.php
│   │   └── TestSyncCommand.php
│   ├── Contracts
│   │   └── InnoditeUserPermissions.php
│   ├── Database
│   │   └── Seeders
│   │       └── InnoditeModuleSeeder.php
│   ├── Generators
│   │   ├── Components
│   │   │   ├── AbstractComponentGenerator.php
│   │   │   ├── ConsoleCommandGenerator.php
│   │   │   ├── ControllerGenerator.php
│   │   │   ├── ExceptionGenerator.php
│   │   │   ├── Factory
│   │   │   │   ├── Contracts
│   │   │   │   │   └── AttributeValueStrategy.php
│   │   │   │   ├── FactoryGenerator.php
│   │   │   │   └── Strategies
│   │   │   │       ├── BooleanStrategy.php
│   │   │   │       ├── DateStrategy.php
│   │   │   │       ├── EnumStrategy.php
│   │   │   │       ├── ForeignIdStrategy.php
│   │   │   │       ├── ForeignStrategy.php
│   │   │   │       ├── IntegerStrategy.php
│   │   │   │       └── TextStrategy.php
│   │   │   ├── JobGenerator.php
│   │   │   ├── MigrationGenerator.php
│   │   │   ├── ModelGenerator.php
│   │   │   ├── ModuleGenerator.php
│   │   │   ├── NotificationGenerator.php
│   │   │   ├── ProviderGenerator.php
│   │   │   ├── RepositoryGenerator.php
│   │   │   ├── RequestGenerator.php
│   │   │   ├── RouteGenerator.php
│   │   │   ├── SeederGenerator.php
│   │   │   ├── ServiceGenerator.php
│   │   │   ├── SupportTestGenerator.php
│   │   │   ├── TestGenerator.php
│   │   │   └── VueGenerator.php
│   │   └── Concerns
│   │       └── HasStubs.php
│   ├── LaravelModuleMakerServiceProvider.php
│   ├── Middleware
│   │   └── InnoditeContextBridge.php
│   ├── Services
│   │   ├── MigrationPlanResolver.php
│   │   ├── MigrationTargetService.php
│   │   ├── ModuleAuditor.php
│   │   ├── RouteInjectionService.php
│   │   └── TestContextConfigService.php
│   ├── Support
│   │   └── ContextResolver.php
│   └── Traits
│       └── RendersInertiaModule.php
├── stubs
│   ├── contexts.json
│   ├── contextual
│   │   ├── Central
│   │   │   ├── console-command.stub
│   │   │   ├── controller.stub
│   │   │   ├── exception.stub
│   │   │   ├── factory.stub
│   │   │   ├── job.stub
│   │   │   ├── migration.stub
│   │   │   ├── model.stub
│   │   │   ├── notification.stub
│   │   │   ├── provider.stub
│   │   │   ├── repository-interface.stub
│   │   │   ├── repository.stub
│   │   │   ├── request-store.stub
│   │   │   ├── request-update.stub
│   │   │   ├── request.stub
│   │   │   ├── route-api.stub
│   │   │   ├── route-tenant.stub
│   │   │   ├── route-web.stub
│   │   │   ├── seeder.stub
│   │   │   ├── service-interface.stub
│   │   │   ├── service.stub
│   │   │   ├── test-support.stub
│   │   │   ├── test-unit.stub
│   │   │   ├── test.stub
│   │   │   ├── vue-create.stub
│   │   │   ├── vue-edit.stub
│   │   │   ├── vue-index.stub
│   │   │   └── vue-show.stub
│   │   ├── Shared
│   │   │   ├── console-command.stub
│   │   │   ├── controller.stub
│   │   │   ├── exception.stub
│   │   │   ├── factory.stub
│   │   │   ├── job.stub
│   │   │   ├── migration.stub
│   │   │   ├── model.stub
│   │   │   ├── notification.stub
│   │   │   ├── provider.stub
│   │   │   ├── repository-interface.stub
│   │   │   ├── repository.stub
│   │   │   ├── request-store.stub
│   │   │   ├── request-update.stub
│   │   │   ├── request.stub
│   │   │   ├── route-api.stub
│   │   │   ├── route-tenant.stub
│   │   │   ├── route-web.stub
│   │   │   ├── seeder.stub
│   │   │   ├── service-interface.stub
│   │   │   ├── service.stub
│   │   │   ├── test-support.stub
│   │   │   ├── test-unit.stub
│   │   │   ├── test.stub
│   │   │   ├── vue-create.stub
│   │   │   ├── vue-edit.stub
│   │   │   ├── vue-index.stub
│   │   │   └── vue-show.stub
│   │   ├── TenantName
│   │   │   ├── console-command.stub
│   │   │   ├── controller.stub
│   │   │   ├── exception.stub
│   │   │   ├── factory.stub
│   │   │   ├── job.stub
│   │   │   ├── migration.stub
│   │   │   ├── model.stub
│   │   │   ├── notification.stub
│   │   │   ├── provider.stub
│   │   │   ├── repository-interface.stub
│   │   │   ├── repository.stub
│   │   │   ├── request-store.stub
│   │   │   ├── request-update.stub
│   │   │   ├── request.stub
│   │   │   ├── route-api.stub
│   │   │   ├── route-tenant.stub
│   │   │   ├── route-web.stub
│   │   │   ├── seeder.stub
│   │   │   ├── service-interface.stub
│   │   │   ├── service.stub
│   │   │   ├── test-support.stub
│   │   │   ├── test-unit.stub
│   │   │   ├── test.stub
│   │   │   ├── vue-create.stub
│   │   │   ├── vue-edit.stub
│   │   │   ├── vue-index.stub
│   │   │   └── vue-show.stub
│   │   ├── TenantShared
│   │   │   ├── console-command.stub
│   │   │   ├── controller.stub
│   │   │   ├── exception.stub
│   │   │   ├── factory.stub
│   │   │   ├── job.stub
│   │   │   ├── migration.stub
│   │   │   ├── model.stub
│   │   │   ├── notification.stub
│   │   │   ├── provider.stub
│   │   │   ├── repository-interface.stub
│   │   │   ├── repository.stub
│   │   │   ├── request-store.stub
│   │   │   ├── request-update.stub
│   │   │   ├── request.stub
│   │   │   ├── route-api.stub
│   │   │   ├── route-tenant.stub
│   │   │   ├── route-web.stub
│   │   │   ├── seeder.stub
│   │   │   ├── service-interface.stub
│   │   │   ├── service.stub
│   │   │   ├── test-support.stub
│   │   │   ├── test-unit.stub
│   │   │   ├── test.stub
│   │   │   ├── vue-create.stub
│   │   │   ├── vue-edit.stub
│   │   │   ├── vue-index.stub
│   │   │   └── vue-show.stub
│   │   ├── console-command.stub
│   │   ├── controller.stub
│   │   ├── exception.stub
│   │   ├── factory.stub
│   │   ├── job.stub
│   │   ├── migration.stub
│   │   ├── model.stub
│   │   ├── notification.stub
│   │   ├── provider.stub
│   │   ├── repository-interface.stub
│   │   ├── repository.stub
│   │   ├── request-store.stub
│   │   ├── request-update.stub
│   │   ├── request.stub
│   │   ├── route-api.stub
│   │   ├── route-tenant.stub
│   │   ├── route-web.stub
│   │   ├── seeder.stub
│   │   ├── service-interface.stub
│   │   ├── service.stub
│   │   ├── test-support.stub
│   │   ├── test-unit.stub
│   │   ├── test.stub
│   │   ├── vue-create.stub
│   │   ├── vue-edit.stub
│   │   ├── vue-index.stub
│   │   └── vue-show.stub
│   └── resources
│       └── js
│           └── Composables
│               ├── useModuleContext.js
│               └── usePermissions.js
└── tests
    ├── Feature
    │   ├── MakeModuleCommandTest.php
    │   ├── MigrateOneCommandTest.php
    │   ├── MigratePlanCommandTest.php
    │   ├── MigrationSyncCommandTest.php
    │   ├── SeedOneCommandTest.php
    │   ├── TestModuleCommandEnvTest.php
    │   └── TestSyncCommandTest.php
    ├── Pest.php
    ├── TestCase.php
    └── Unit
        └── ModuleAuditorTest.php

34 directories, 208 files
