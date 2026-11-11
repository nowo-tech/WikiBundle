import { c as e } from "./chunk-DSNLSHJZ-DRpVu4wY.js";
import { m as t } from "./src-ckL58JCF.js";
import { n } from "./chunk-Y2CYZVJY-fpkXlLv-.js";
// node_modules/.pnpm/mermaid@12.2.0/node_modules/mermaid/dist/chunks/mermaid.core/chunk-VQ5W62QI.mjs
var r = /* @__PURE__ */ n((n, r, o, s) => {
	n.attr("class", o);
	let { width: c, height: l, x: u, y: d } = i(n, r);
	e(n, l, c, s);
	let f = a(u, d, c, l, r);
	n.attr("viewBox", f), t.debug(`viewBox configured: ${f} with padding: ${r}`);
}, "setupViewPortForSVG"), i = /* @__PURE__ */ n((e, t) => {
	let n = e.node()?.getBBox() || {
		width: 0,
		height: 0,
		x: 0,
		y: 0
	};
	return {
		width: n.width + t * 2,
		height: n.height + t * 2,
		x: n.x,
		y: n.y
	};
}, "calculateDimensionsWithPadding"), a = /* @__PURE__ */ n((e, t, n, r, i) => `${e - i} ${t - i} ${n} ${r}`, "createViewBox");
export { r as t };
