# Use ./composer to avoid PHP 8.4 deprecation notices from Composer 2.7.x
COMPOSER := ./composer

.PHONY: install up update test lint analyse docs
install:
	$(COMPOSER) install
up update:
	$(COMPOSER) update
test:
	$(COMPOSER) test
lint:
	$(COMPOSER) lint
analyse:
	$(COMPOSER) analyse
docs:
	php packages/docit/artisan docit
