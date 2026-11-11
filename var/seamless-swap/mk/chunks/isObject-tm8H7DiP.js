// node_modules/.pnpm/lodash-es@4.18.1/node_modules/lodash-es/_freeGlobal.js
var e = typeof global == "object" && global && global.Object === Object && global, t = typeof self == "object" && self && self.Object === Object && self, n = e || t || Function("return this")(), r = n.Symbol, i = Object.prototype, a = i.hasOwnProperty, o = i.toString, s = r ? r.toStringTag : void 0;
function c(e) {
	var t = a.call(e, s), n = e[s];
	try {
		e[s] = void 0;
		var r = !0;
	} catch {}
	var i = o.call(e);
	return r && (t ? e[s] = n : delete e[s]), i;
}
// node_modules/.pnpm/lodash-es@4.18.1/node_modules/lodash-es/_objectToString.js
var l = Object.prototype.toString;
function u(e) {
	return l.call(e);
}
// node_modules/.pnpm/lodash-es@4.18.1/node_modules/lodash-es/_baseGetTag.js
var d = "[object Null]", f = "[object Undefined]", p = r ? r.toStringTag : void 0;
function m(e) {
	return e == null ? e === void 0 ? f : d : p && p in Object(e) ? c(e) : u(e);
}
// node_modules/.pnpm/lodash-es@4.18.1/node_modules/lodash-es/isObjectLike.js
function h(e) {
	return typeof e == "object" && !!e;
}
// node_modules/.pnpm/lodash-es@4.18.1/node_modules/lodash-es/isSymbol.js
var g = "[object Symbol]";
function _(e) {
	return typeof e == "symbol" || h(e) && m(e) == g;
}
// node_modules/.pnpm/lodash-es@4.18.1/node_modules/lodash-es/isObject.js
function v(e) {
	var t = typeof e;
	return e != null && (t == "object" || t == "function");
}
export { r as a, m as i, _ as n, n as o, h as r, e as s, v as t };
