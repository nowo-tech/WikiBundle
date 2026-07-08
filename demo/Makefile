.PHONY: up-symfony8 release-check

up-symfony8:
	@$(MAKE) -C symfony8 up

release-check:
	@$(MAKE) -C symfony8 update-bundle
	@$(MAKE) -C symfony8 test
