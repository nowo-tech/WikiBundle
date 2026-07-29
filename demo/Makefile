.PHONY: up-symfony8 release-check demo-smoke

up-symfony8:
	@$(MAKE) -C symfony8 up

release-check:
	@$(MAKE) -C symfony8 update-bundle
	@$(MAKE) -C symfony8 test

# REQ-TEST-011: boot FrankenPHP demo and assert HTTP 200 on /tools/wiki
demo-smoke:
	@PORT=$$(grep "^PORT=" symfony8/.env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" symfony8/.env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=8025; \
	echo "Demo smoke: symfony8 (http://127.0.0.1:$$PORT/tools/wiki)"; \
	$(MAKE) -C symfony8 up || exit 1; \
	code=000; \
	i=0; \
	while [ $$i -lt 60 ]; do \
		code=$$(curl -sL -o /dev/null -w "%{http_code}" "http://127.0.0.1:$$PORT/tools/wiki" || true); \
		[ -z "$$code" ] && code=000; \
		if [ "$$code" = "200" ]; then break; fi; \
		i=$$((i+1)); \
		sleep 2; \
	done; \
	$(MAKE) -C symfony8 down 2>/dev/null || true; \
	if [ "$$code" != "200" ]; then echo "symfony8 failed with HTTP $$code"; exit 1; fi; \
	echo "symfony8 OK (HTTP $$code)"
