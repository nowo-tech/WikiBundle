import { $t as e, f as t, l as n, m as r, ot as i, r as a, st as o, ut as s } from "./lib-9QpRoHAM.js";
import { n as c, u as l } from "./lib-DaU_YkmU.js";
import { _ as u, d, l as ee, m as te, o as f, p as ne, s as p, u as m } from "./floating-ui.dom-DE0KslHM.js";
import { t as h } from "./lib-B0HMIP_r.js";
import { i as g, l as _, w as v, x as y, y as b } from "./link-tooltip-CrklcZfC.js";
import { D as re, E as ie, O as x, S, T as ae, _ as oe, g as C, i as w, l as T, m as se, n as ce, p as E, r as le, s as ue, t as D, u as de, v as fe, w as pe, x as me, y as he } from "./lib-Cs-8MFWq.js";
e([], "FeaturesCtx"), e({}, "CrepeCtx");
function O(e) {
	return e.use("FeaturesCtx");
}
function ge(e) {
	return (t) => {
		O(t).update((t) => t.includes(e) ? t : [...t, e]);
	};
}
var k = /* @__PURE__ */ ((e) => (e.CodeMirror = "code-mirror", e.ListItem = "list-item", e.LinkTooltip = "link-tooltip", e.Cursor = "cursor", e.ImageBlock = "image-block", e.BlockEdit = "block-edit", e.Toolbar = "toolbar", e.Placeholder = "placeholder", e.Table = "table", e.Latex = "latex", e.TopBar = "top-bar", e.AI = "ai", e))(k || {}), _e = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    width=\"24\"\n    height=\"24\"\n    viewBox=\"0 0 24 24\"\n  >\n    <path\n      d=\"M8.85758 18.625C8.4358 18.625 8.07715 18.4772 7.78163 18.1817C7.48613 17.8862 7.33838 17.5275 7.33838 17.1058V6.8942C7.33838 6.47242 7.48613 6.11377 7.78163 5.81825C8.07715 5.52275 8.4358 5.375 8.85758 5.375H12.1999C13.2191 5.375 14.1406 5.69231 14.9643 6.32693C15.788 6.96154 16.1999 7.81603 16.1999 8.89038C16.1999 9.63779 16.0194 10.2471 15.6585 10.7183C15.2976 11.1894 14.9088 11.5314 14.4922 11.7442C15.005 11.9211 15.4947 12.2708 15.9614 12.7933C16.428 13.3157 16.6614 14.0192 16.6614 14.9038C16.6614 16.182 16.1902 17.1217 15.2479 17.723C14.3056 18.3243 13.3563 18.625 12.3999 18.625H8.85758ZM9.4883 16.6327H12.3191C13.1063 16.6327 13.6627 16.4141 13.9884 15.9769C14.314 15.5397 14.4768 15.1205 14.4768 14.7192C14.4768 14.3179 14.314 13.8987 13.9884 13.4615C13.6627 13.0243 13.0909 12.8057 12.273 12.8057H9.4883V16.6327ZM9.4883 10.875H12.0826C12.6903 10.875 13.172 10.7013 13.5278 10.3539C13.8836 10.0064 14.0615 9.59037 14.0615 9.10575C14.0615 8.59035 13.8733 8.16918 13.497 7.84225C13.1207 7.51533 12.6595 7.35188 12.1133 7.35188H9.4883V10.875Z\"\n    />\n  </svg>\n", ve = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    width=\"24\"\n    height=\"24\"\n    viewBox=\"0 0 24 24\"\n  >\n    <g clip-path=\"url(#clip0_977_8070)\">\n      <path\n        d=\"M4 10.5C3.17 10.5 2.5 11.17 2.5 12C2.5 12.83 3.17 13.5 4 13.5C4.83 13.5 5.5 12.83 5.5 12C5.5 11.17 4.83 10.5 4 10.5ZM4 4.5C3.17 4.5 2.5 5.17 2.5 6C2.5 6.83 3.17 7.5 4 7.5C4.83 7.5 5.5 6.83 5.5 6C5.5 5.17 4.83 4.5 4 4.5ZM4 16.5C3.17 16.5 2.5 17.18 2.5 18C2.5 18.82 3.18 19.5 4 19.5C4.82 19.5 5.5 18.82 5.5 18C5.5 17.18 4.83 16.5 4 16.5ZM8 19H20C20.55 19 21 18.55 21 18C21 17.45 20.55 17 20 17H8C7.45 17 7 17.45 7 18C7 18.55 7.45 19 8 19ZM8 13H20C20.55 13 21 12.55 21 12C21 11.45 20.55 11 20 11H8C7.45 11 7 11.45 7 12C7 12.55 7.45 13 8 13ZM7 6C7 6.55 7.45 7 8 7H20C20.55 7 21 6.55 21 6C21 5.45 20.55 5 20 5H8C7.45 5 7 5.45 7 6Z\"\n      />\n    </g>\n    <defs>\n      <clipPath id=\"clip0_977_8070\">\n        <rect width=\"24\" height=\"24\" />\n      </clipPath>\n    </defs>\n  </svg>\n", A = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    fill=\"none\"\n    viewBox=\"0 0 24 24\"\n    stroke-width=\"1.5\"\n    stroke=\"currentColor\"\n    class=\"w-6 h-6\"\n  >\n    <path\n      stroke-linecap=\"round\"\n      stroke-linejoin=\"round\"\n      d=\"M19.5 8.25l-7.5 7.5-7.5-7.5\"\n    />\n  </svg>\n", j = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    width=\"24\"\n    height=\"24\"\n    viewBox=\"0 0 24 24\"\n  >\n    <g clip-path=\"url(#clip0_977_8081)\">\n      <path\n        d=\"M9.4 16.6L4.8 12L9.4 7.4L8 6L2 12L8 18L9.4 16.6ZM14.6 16.6L19.2 12L14.6 7.4L16 6L22 12L16 18L14.6 16.6Z\"\n      />\n    </g>\n    <defs>\n      <clipPath id=\"clip0_977_8081\">\n        <rect width=\"24\" height=\"24\" />\n      </clipPath>\n    </defs>\n  </svg>\n", M = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    width=\"24\"\n    height=\"24\"\n    viewBox=\"0 0 24 24\"\n  >\n    <path\n      d=\"M3 3h18a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zm1 2v14h16V5H4zm8 10h6v2h-6v-2zm-3.333-3L5.838 9.172l1.415-1.415L11.495 12l-4.242 4.243-1.415-1.415L8.667 12z\"\n    />\n  </svg>\n", N = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    width=\"24\"\n    height=\"24\"\n    viewBox=\"0 0 24 24\"\n  >\n    <g clip-path=\"url(#clip0_977_7900)\">\n      <path\n        fill-rule=\"evenodd\"\n        clip-rule=\"evenodd\"\n        d=\"M19 13H5C4.45 13 4 12.55 4 12C4 11.45 4.45 11 5 11H19C19.55 11 20 11.45 20 12C20 12.55 19.55 13 19 13Z\"\n      />\n    </g>\n    <defs>\n      <clipPath id=\"clip0_977_7900\">\n        <rect width=\"24\" height=\"24\" />\n      </clipPath>\n    </defs>\n  </svg>\n", P = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    width=\"24\"\n    height=\"24\"\n    viewBox=\"0 0 24 24\"\n  >\n    <g clip-path=\"url(#clip0_977_8075)\">\n      <path\n        d=\"M19 5V19H5V5H19ZM19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM14.14 11.86L11.14 15.73L9 13.14L6 17H18L14.14 11.86Z\"\n      />\n    </g>\n    <defs>\n      <clipPath id=\"clip0_977_8075\">\n        <rect width=\"24\" height=\"24\" />\n      </clipPath>\n    </defs>\n  </svg>\n", F = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    width=\"24\"\n    height=\"24\"\n    viewBox=\"0 0 24 24\"\n  >\n    <path\n      d=\"M6.29811 18.625C6.04505 18.625 5.83115 18.5375 5.65641 18.3626C5.48166 18.1877 5.39429 17.9736 5.39429 17.7203C5.39429 17.467 5.48166 17.2532 5.65641 17.0788C5.83115 16.9045 6.04505 16.8173 6.29811 16.8173H9.21159L12.452 7.18265H9.53851C9.28545 7.18265 9.07155 7.0952 8.89681 6.9203C8.72206 6.7454 8.63469 6.5313 8.63469 6.278C8.63469 6.02472 8.72206 5.81089 8.89681 5.63652C9.07155 5.46217 9.28545 5.375 9.53851 5.375H16.8847C17.1377 5.375 17.3516 5.46245 17.5264 5.63735C17.7011 5.81225 17.7885 6.02634 17.7885 6.27962C17.7885 6.53293 17.7011 6.74676 17.5264 6.92113C17.3516 7.09548 17.1377 7.18265 16.8847 7.18265H14.2789L11.0385 16.8173H13.6443C13.8973 16.8173 14.1112 16.9048 14.286 17.0797C14.4607 17.2546 14.5481 17.4687 14.5481 17.722C14.5481 17.9752 14.4607 18.1891 14.286 18.3634C14.1112 18.5378 13.8973 18.625 13.6443 18.625H6.29811Z\"\n    />\n  </svg>\n", ye = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    width=\"24\"\n    height=\"24\"\n    viewBox=\"0 0 24 24\"\n  >\n    <path\n      d=\"M17.0385 19.5003V16.5388H14.0769V15.0388H17.0385V12.0773H18.5384V15.0388H21.5V16.5388H18.5384V19.5003H17.0385ZM10.8077 16.5388H7.03845C5.78282 16.5388 4.7125 16.0963 3.8275 15.2114C2.9425 14.3266 2.5 13.2564 2.5 12.0009C2.5 10.7454 2.9425 9.67504 3.8275 8.78979C4.7125 7.90454 5.78282 7.46191 7.03845 7.46191H10.8077V8.96186H7.03845C6.1987 8.96186 5.48235 9.25834 4.8894 9.85129C4.29645 10.4442 3.99998 11.1606 3.99998 12.0003C3.99998 12.8401 4.29645 13.5564 4.8894 14.1494C5.48235 14.7423 6.1987 15.0388 7.03845 15.0388H10.8077V16.5388ZM8.25 12.7503V11.2504H15.75V12.7503H8.25ZM21.5 12.0003H20C20 11.1606 19.7035 10.4442 19.1106 9.85129C18.5176 9.25834 17.8013 8.96186 16.9615 8.96186H13.1923V7.46191H16.9615C18.2171 7.46191 19.2875 7.90441 20.1725 8.78939C21.0575 9.67439 21.5 10.7447 21.5 12.0003Z\"\n    />\n  </svg>\n", be = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    width=\"24\"\n    height=\"24\"\n    viewBox=\"0 0 24 24\"\n  >\n    <g clip-path=\"url(#clip0_977_8067)\">\n      <path\n        d=\"M8 7H20C20.55 7 21 6.55 21 6C21 5.45 20.55 5 20 5H8C7.45 5 7 5.45 7 6C7 6.55 7.45 7 8 7ZM20 17H8C7.45 17 7 17.45 7 18C7 18.55 7.45 19 8 19H20C20.55 19 21 18.55 21 18C21 17.45 20.55 17 20 17ZM20 11H8C7.45 11 7 11.45 7 12C7 12.55 7.45 13 8 13H20C20.55 13 21 12.55 21 12C21 11.45 20.55 11 20 11ZM4.5 16H2.5C2.22 16 2 16.22 2 16.5C2 16.78 2.22 17 2.5 17H4V17.5H3.5C3.22 17.5 3 17.72 3 18C3 18.28 3.22 18.5 3.5 18.5H4V19H2.5C2.22 19 2 19.22 2 19.5C2 19.78 2.22 20 2.5 20H4.5C4.78 20 5 19.78 5 19.5V16.5C5 16.22 4.78 16 4.5 16ZM2.5 5H3V7.5C3 7.78 3.22 8 3.5 8C3.78 8 4 7.78 4 7.5V4.5C4 4.22 3.78 4 3.5 4H2.5C2.22 4 2 4.22 2 4.5C2 4.78 2.22 5 2.5 5ZM4.5 10H2.5C2.22 10 2 10.22 2 10.5C2 10.78 2.22 11 2.5 11H3.8L2.12 12.96C2.04 13.05 2 13.17 2 13.28V13.5C2 13.78 2.22 14 2.5 14H4.5C4.78 14 5 13.78 5 13.5C5 13.22 4.78 13 4.5 13H3.2L4.88 11.04C4.96 10.95 5 10.83 5 10.72V10.5C5 10.22 4.78 10 4.5 10Z\"\n      />\n    </g>\n    <defs>\n      <clipPath id=\"clip0_977_8067\">\n        <rect width=\"24\" height=\"24\" />\n      </clipPath>\n    </defs>\n  </svg>\n", xe = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    width=\"24\"\n    height=\"24\"\n    viewBox=\"0 0 24 24\"\n  >\n    <g clip-path=\"url(#clip0_977_7897)\">\n      <path\n        d=\"M7.17 17C7.68 17 8.15 16.71 8.37 16.26L9.79 13.42C9.93 13.14 10 12.84 10 12.53V8C10 7.45 9.55 7 9 7H5C4.45 7 4 7.45 4 8V12C4 12.55 4.45 13 5 13H7L5.97 15.06C5.52 15.95 6.17 17 7.17 17ZM17.17 17C17.68 17 18.15 16.71 18.37 16.26L19.79 13.42C19.93 13.14 20 12.84 20 12.53V8C20 7.45 19.55 7 19 7H15C14.45 7 14 7.45 14 8V12C14 12.55 14.45 13 15 13H17L15.97 15.06C15.52 15.95 16.17 17 17.17 17Z\"\n      />\n    </g>\n    <defs>\n      <clipPath id=\"clip0_977_7897\">\n        <rect width=\"24\" height=\"24\" />\n      </clipPath>\n    </defs>\n  </svg>\n", I = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    width=\"24\"\n    height=\"24\"\n    viewBox=\"0 0 24 24\"\n  >\n    <path\n      d=\"M3.25 13.7404C3.0375 13.7404 2.85938 13.6684 2.71563 13.5246C2.57188 13.3808 2.5 13.2026 2.5 12.99C2.5 12.7774 2.57188 12.5993 2.71563 12.4558C2.85938 12.3122 3.0375 12.2404 3.25 12.2404H20.75C20.9625 12.2404 21.1406 12.3123 21.2843 12.4561C21.4281 12.5999 21.5 12.7781 21.5 12.9907C21.5 13.2033 21.4281 13.3814 21.2843 13.525C21.1406 13.6686 20.9625 13.7404 20.75 13.7404H3.25ZM10.9423 10.2596V6.62495H6.5673C6.2735 6.62495 6.02377 6.52201 5.8181 6.31613C5.61245 6.11026 5.50963 5.86027 5.50963 5.56615C5.50963 5.27205 5.61245 5.02083 5.8181 4.8125C6.02377 4.60417 6.2735 4.5 6.5673 4.5H17.4423C17.7361 4.5 17.9858 4.60294 18.1915 4.80883C18.3971 5.01471 18.5 5.2647 18.5 5.5588C18.5 5.85292 18.3971 6.10413 18.1915 6.31245C17.9858 6.52078 17.7361 6.62495 17.4423 6.62495H13.0673V10.2596H10.9423ZM10.9423 15.7211H13.0673V18.4423C13.0673 18.7361 12.9643 18.9858 12.7584 19.1915C12.5526 19.3971 12.3026 19.5 12.0085 19.5C11.7144 19.5 11.4631 19.3962 11.2548 19.1887C11.0465 18.9811 10.9423 18.7291 10.9423 18.4327V15.7211Z\"\n    />\n  </svg>\n", Se = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    width=\"24\"\n    height=\"24\"\n    viewBox=\"0 0 24 24\"\n  >\n    <g clip-path=\"url(#clip0_977_8078)\">\n      <path\n        d=\"M20 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H20C21.1 21 22 20.1 22 19V5C22 3.9 21.1 3 20 3ZM20 5V8H5V5H20ZM15 19H10V10H15V19ZM5 10H8V19H5V10ZM17 19V10H20V19H17Z\"\n      />\n    </g>\n    <defs>\n      <clipPath id=\"clip0_977_8078\">\n        <rect width=\"24\" height=\"24\" />\n      </clipPath>\n    </defs>\n  </svg>\n", Ce = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    width=\"24\"\n    height=\"24\"\n    viewBox=\"0 0 24 24\"\n  >\n    <path\n      d=\"M5.66936 16.3389L9.39244 12.6158C9.54115 12.4671 9.71679 12.3937 9.91936 12.3957C10.1219 12.3976 10.2975 12.4761 10.4463 12.6312C10.5847 12.7823 10.654 12.9585 10.654 13.1599C10.654 13.3613 10.5847 13.5363 10.4463 13.6851L6.32704 17.8197C6.14627 18.0004 5.93538 18.0908 5.69436 18.0908C5.45333 18.0908 5.24243 18.0004 5.06166 17.8197L3.01744 15.7754C2.87899 15.637 2.81136 15.4629 2.81456 15.2533C2.81776 15.0437 2.88859 14.8697 3.02706 14.7312C3.16551 14.5928 3.34008 14.5235 3.55076 14.5235C3.76144 14.5235 3.93494 14.5928 4.07126 14.7312L5.66936 16.3389ZM5.66936 8.72359L9.39244 5.00049C9.54115 4.85177 9.71679 4.77838 9.91936 4.78031C10.1219 4.78223 10.2975 4.86075 10.4463 5.01586C10.5847 5.16691 10.654 5.34314 10.654 5.54454C10.654 5.74592 10.5847 5.92097 10.4463 6.06969L6.32704 10.2043C6.14627 10.3851 5.93538 10.4755 5.69436 10.4755C5.45333 10.4755 5.24243 10.3851 5.06166 10.2043L3.01744 8.16009C2.87899 8.02162 2.81136 7.84759 2.81456 7.63799C2.81776 7.42837 2.88859 7.25433 3.02706 7.11586C3.16551 6.97741 3.34008 6.90819 3.55076 6.90819C3.76144 6.90819 3.93494 6.97741 4.07126 7.11586L5.66936 8.72359ZM13.7597 16.5581C13.5472 16.5581 13.3691 16.4862 13.2253 16.3424C13.0816 16.1986 13.0097 16.0204 13.0097 15.8078C13.0097 15.5952 13.0816 15.4171 13.2253 15.2735C13.3691 15.13 13.5472 15.0582 13.7597 15.0582H20.7597C20.9722 15.0582 21.1503 15.1301 21.2941 15.2739C21.4378 15.4177 21.5097 15.5959 21.5097 15.8085C21.5097 16.0211 21.4378 16.1992 21.2941 16.3427C21.1503 16.4863 20.9722 16.5581 20.7597 16.5581H13.7597ZM13.7597 8.94276C13.5472 8.94276 13.3691 8.87085 13.2253 8.72704C13.0816 8.58324 13.0097 8.40504 13.0097 8.19244C13.0097 7.97985 13.0816 7.80177 13.2253 7.65819C13.3691 7.5146 13.5472 7.44281 13.7597 7.44281H20.7597C20.9722 7.44281 21.1503 7.51471 21.2941 7.65851C21.4378 7.80233 21.5097 7.98053 21.5097 8.19311C21.5097 8.40571 21.4378 8.5838 21.2941 8.72739C21.1503 8.87097 20.9722 8.94276 20.7597 8.94276H13.7597Z\"\n    />\n  </svg>\n", we = "\n  <svg\n    xmlns=\"http://www.w3.org/2000/svg\"\n    width=\"32\"\n    height=\"32\"\n    viewBox=\"0 0 24 24\"\n  >\n    <path\n      fill=\"currentColor\"\n      d=\"M7 19v-.808L13.096 12L7 5.808V5h10v1.25H9.102L14.727 12l-5.625 5.77H17V19z\"\n    />\n  </svg>\n", L = (e) => {
	throw TypeError(e);
}, R = (e, t, n) => t.has(e) || L("Cannot " + n), z = (e, t, n) => (R(e, t, "read from private field"), n ? n.call(e) : t.get(e)), B = (e, t, n) => t.has(e) ? L("Cannot add the same private member more than once") : t instanceof WeakSet ? t.add(e) : t.set(e, n), Te = (e, t, n, r) => (R(e, t, "write to private field"), t.set(e, n), n), V, H, Ee = class {
	constructor() {
		B(this, V, []), this.clear = () => (Te(this, V, []), this), B(this, H, (e) => {
			let t = {
				group: e,
				addItem: (n, r) => {
					let i = {
						...r,
						key: n
					};
					return e.items.push(i), t;
				},
				clear: () => (e.items = [], t)
			};
			return t;
		}), this.addGroup = (e, t) => {
			let n = {
				key: e,
				label: t,
				items: []
			};
			return z(this, V).push(n), z(this, H).call(this, n);
		}, this.getGroup = (e) => {
			let t = z(this, V).find((t) => t.key === e);
			if (!t) throw Error(`Group with key ${e} not found`);
			return z(this, H).call(this, t);
		}, this.build = () => z(this, V);
	}
};
V = /* @__PURE__ */ new WeakMap(), H = /* @__PURE__ */ new WeakMap();
var U = [
	{
		label: "Paragraph",
		level: null
	},
	{
		label: "Heading 1",
		level: 1
	},
	{
		label: "Heading 2",
		level: 2
	},
	{
		label: "Heading 3",
		level: 3
	},
	{
		label: "Heading 4",
		level: 4
	},
	{
		label: "Heading 5",
		level: 5
	},
	{
		label: "Heading 6",
		level: 6
	}
];
function De(e, t) {
	let n = t ?? U, { $from: i } = e.get(r).state.selection, a = i.parent, o = n.find((e) => e.level === null) ?? n[0];
	if (a.type === T.type(e)) {
		let e = a.attrs.level;
		return n.find((t) => t.level === e) ?? o;
	}
	return o;
}
function Oe(e, t) {
	let r = e.get(n);
	if (t == null) {
		let t = he.type(e);
		r.call(S.key, { nodeType: t });
	} else {
		let n = T.type(e);
		r.call(S.key, {
			nodeType: n,
			attrs: { level: t }
		});
	}
}
function W(e, t) {
	if (e.get(n).call(se.key, t)) return !0;
	let { state: i } = e.get(r);
	if (i.storedMarks) return i.storedMarks.some((e) => e.type === t);
	if (i.selection instanceof s) {
		let { $cursor: e } = i.selection;
		if (e) return e.marks().some((e) => e.type === t);
	}
	return !1;
}
function ke(e, t) {
	let n = e ?? U;
	return {
		icon: "",
		active: () => !1,
		selector: {
			chevronIcon: t ?? A,
			activeLabel: (e) => De(e, n).label,
			options: n.map((e) => ({
				label: e.label,
				onSelect: (t) => Oe(t, e.level)
			}))
		}
	};
}
function Ae(e, t) {
	var i;
	let a = t && O(t).get(), o = a?.includes(k.Latex), s = a?.includes(k.ImageBlock), c = a?.includes(k.Table), l = new Ee();
	l.addGroup("heading", "Heading").addItem("heading-selector", { ...ke(e?.headingOptions, e?.chevronDownIcon) }), l.addGroup("formatting", "Formatting").addItem("bold", {
		icon: e?.boldIcon ?? _e,
		active: (e) => W(e, pe.type(e)),
		onRun: (e) => {
			e.get(n).call(re.key);
		}
	}).addItem("italic", {
		icon: e?.italicIcon ?? F,
		active: (e) => W(e, ue.type(e)),
		onRun: (e) => {
			e.get(n).call(ae.key);
		}
	}).addItem("strikethrough", {
		icon: e?.strikethroughIcon ?? I,
		active: (e) => W(e, b.type(e)),
		onRun: (e) => {
			e.get(n).call(y.key);
		}
	}).addItem("code", {
		icon: e?.codeIcon ?? j,
		active: (e) => W(e, E.type(e)),
		onRun: (e) => {
			let t = e.get(r), { state: i } = t;
			if (i.selection.empty) {
				let n = E.type(e);
				W(e, n) ? t.dispatch(i.tr.removeStoredMark(n)) : t.dispatch(i.tr.addStoredMark(n.create()));
			} else e.get(n).call(ie.key);
		}
	}), l.addGroup("list", "List").addItem("bullet-list", {
		icon: e?.bulletListIcon ?? ve,
		active: () => !1,
		onRun: (e) => {
			let t = e.get(n), r = le.type(e);
			t.call(x.key, { nodeType: r });
		}
	}).addItem("ordered-list", {
		icon: e?.orderedListIcon ?? be,
		active: () => !1,
		onRun: (e) => {
			let t = e.get(n), r = fe.type(e);
			t.call(x.key, { nodeType: r });
		}
	}).addItem("task-list", {
		icon: e?.taskListIcon ?? Ce,
		active: () => !1,
		onRun: (e) => {
			let t = e.get(n), r = oe.type(e);
			t.call(x.key, {
				nodeType: r,
				attrs: { checked: !1 }
			});
		}
	});
	let u = l.addGroup("insert", "Insert");
	u.addItem("link", {
		icon: e?.linkIcon ?? ye,
		active: (e) => W(e, C.type(e)),
		onRun: (e) => {
			let t = e.get(r), { state: i } = t, a = C.type(e);
			i.selection.empty && W(e, a) ? t.dispatch(i.tr.removeStoredMark(a)) : e.get(n).call(g.key);
		}
	}), s && u.addItem("image", {
		icon: e?.imageIcon ?? P,
		active: () => !1,
		onRun: (e) => {
			let t = e.get(n), r = v.type(e);
			t.call(D.key, { nodeType: r });
		}
	}), c && u.addItem("table", {
		icon: e?.tableIcon ?? Se,
		active: () => !1,
		onRun: (e) => {
			let t = e.get(n), { from: i } = e.get(r).state.selection;
			t.call(D.key, { nodeType: _(e, 3, 3) }), t.call(me.key, { pos: i });
		}
	});
	let d = l.addGroup("block", "Block");
	return d.addItem("code-block", {
		icon: e?.codeBlockIcon ?? M,
		active: () => !1,
		onRun: (e) => {
			let t = e.get(n), r = w.type(e);
			t.call(S.key, { nodeType: r });
		}
	}), o && d.addItem("math", {
		icon: e?.mathIcon ?? we,
		active: () => !1,
		onRun: (e) => {
			let t = e.get(n), r = w.type(e);
			t.call(D.key, {
				nodeType: r,
				attrs: { language: "LaTeX" }
			});
		}
	}), l.addGroup("more", "More").addItem("quote", {
		icon: e?.quoteIcon ?? xe,
		active: () => !1,
		onRun: (e) => {
			let t = e.get(n), r = ce.type(e);
			t.call(x.key, { nodeType: r });
		}
	}).addItem("hr", {
		icon: e?.hrIcon ?? N,
		active: () => !1,
		onRun: (e) => {
			let t = e.get(n), r = de.type(e);
			t.call(D.key, { nodeType: r });
		}
	}), (i = e?.buildTopBar) == null || i.call(e, l), l.build();
}
var je = m({
	props: {
		ctx: {
			type: Object,
			required: !0
		},
		version: {
			type: Object,
			required: !0
		},
		config: {
			type: Object,
			required: !1
		}
	},
	setup(e) {
		let { ctx: n, config: i } = e, o = u(null), s = (e) => (t) => {
			t.preventDefault(), n && e(n);
		};
		function c() {
			return n.get(t).status === a.Created;
		}
		function l() {
			e.version.value;
		}
		function p(e) {
			return l(), c() ? e(n) : !1;
		}
		function m(e) {
			return l(), c() ? e.activeLabel(n) : e.options[0]?.label ?? "";
		}
		function g(e, t) {
			t.preventDefault(), t.stopPropagation(), o.value = o.value === e ? null : e;
		}
		let _ = (e) => {
			e.target.closest(".top-bar-heading-selector") || (o.value = null);
		};
		ne(() => {
			window.addEventListener("pointerdown", _);
		}), te(() => {
			window.removeEventListener("pointerdown", _);
		});
		let v = ee(() => Ae(i, n));
		function y(e, t) {
			let r = o.value === e, i = m(t);
			return /* @__PURE__ */ d("div", {
				key: e,
				class: "top-bar-heading-selector"
			}, /* @__PURE__ */ d("button", {
				type: "button",
				class: "top-bar-heading-button",
				onPointerdown: (t) => g(e, t)
			}, /* @__PURE__ */ d("span", { class: "top-bar-heading-label" }, i), t.chevronIcon && /* @__PURE__ */ d("span", { class: "top-bar-chevron" }, /* @__PURE__ */ d(h, { icon: t.chevronIcon }))), r && /* @__PURE__ */ d("div", { class: "top-bar-heading-dropdown" }, t.options.map((t, r) => /* @__PURE__ */ d("button", {
				key: `${e}-${r}`,
				type: "button",
				class: f("top-bar-heading-option", i === t.label && "active"),
				onPointerdown: (e) => {
					e.preventDefault(), e.stopPropagation(), o.value = null, t.onSelect(n);
				}
			}, t.label))));
		}
		function b(e) {
			return /* @__PURE__ */ d("button", {
				key: e.key,
				type: "button",
				class: f("top-bar-item", p(e.active) && "active"),
				onPointerdown: s(e.onRun)
			}, /* @__PURE__ */ d(h, { icon: e.icon }));
		}
		return () => {
			l();
			let e = c() ? n.get(r) : null;
			return e && !e.editable ? null : /* @__PURE__ */ d("div", { class: "top-bar-inner" }, v.value.map((e) => e.items.map((e) => e.selector ? y(e.key, e.selector) : e.onRun ? b(e) : null)).reduce((e, t, n) => {
				if (n === 0) e.push(...t);
				else {
					let r = v.value[n]?.key ?? n;
					e.push(/* @__PURE__ */ d("div", {
						key: `divider-${r}`,
						class: "top-bar-divider"
					}), ...t);
				}
				return e;
			}, []));
		};
	}
}), G = (e) => {
	throw TypeError(e);
}, K = (e, t, n) => t.has(e) || G("Cannot " + n), q = (e, t, n) => (K(e, t, "read from private field"), n ? n.call(e) : t.get(e)), J = (e, t, n) => t.has(e) ? G("Cannot add the same private member more than once") : t instanceof WeakSet ? t.add(e) : t.set(e, n), Y = (e, t, n, r) => (K(e, t, "write to private field"), t.set(e, n), n), X, Z, Q, Me = new o("CREPE_TOP_BAR"), Ne = class {
	constructor(e, t, n) {
		J(this, X), J(this, Z), J(this, Q), this.update = (e, t) => {
			q(this, X).style.display = e.editable ? "" : "none", q(this, Q).value++;
		}, this.destroy = () => {
			q(this, Z).unmount(), q(this, X).remove();
		}, Y(this, Q, u(0));
		let r = document.createElement("div");
		r.className = "milkdown-top-bar";
		let i = p(je, {
			ctx: e,
			config: n,
			version: q(this, Q)
		});
		i.mount(r), Y(this, X, r), Y(this, Z, i);
		let a = t.dom.parentElement;
		a && a.insertBefore(r, a.firstChild), this.update(t);
	}
};
X = /* @__PURE__ */ new WeakMap(), Z = /* @__PURE__ */ new WeakMap(), Q = /* @__PURE__ */ new WeakMap();
var $ = c({ view: () => ({
	update: () => {},
	destroy: () => {}
}) }, "topBarConfig"), Pe = l((e) => {
	let t = e.get($.key);
	return new i({
		key: Me,
		view: t.view
	});
}), Fe = (e, t) => {
	e.config(ge(k.TopBar)).config((e) => {
		e.set($.key, { view: (n) => new Ne(e, n, t) });
	}).use($).use(Pe);
};
export { Fe as topBar };
