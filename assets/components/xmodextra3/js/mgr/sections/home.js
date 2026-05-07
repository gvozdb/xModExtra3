xModExtra3.page.Home = function (config) {
    config = config || {};
    Ext.applyIf(config, {
        components: [{
            xtype: 'xmodextra3-panel-home',
            renderTo: 'xmodextra3-panel-home-div'
        }]
    });
    xModExtra3.page.Home.superclass.constructor.call(this, config);
};
Ext.extend(xModExtra3.page.Home, MODx.Component);
Ext.reg('xmodextra3-page-home', xModExtra3.page.Home);