let xModExtra3 = function (config) {
    config = config || {};
    xModExtra3.superclass.constructor.call(this, config);
};
Ext.extend(xModExtra3, Ext.Component, {
    page: {}, window: {}, grid: {}, tree: {}, panel: {}, combo: {}, config: {}, view: {}, utils: {}
});
Ext.reg('xmodextra3', xModExtra3);

xModExtra3 = new xModExtra3();