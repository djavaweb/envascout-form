var app = new Vue({
  el: '#envascout-wrap',
  data: {
    displayTab: 'envato',
    envato: {
      displayClientSecret: false
    },
    helpscout: {
      displayApiKey: false
    }
  },
  methods: {
    showTab: function (tabName, e) {
      e.preventDefault();
      this.displayTab = tabName;
    },

    reinitClientSecretInputType: function () {
      document.querySelector('#envato_client_secret').setAttribute('type', this.envato.displayClientSecret ? 'text' : 'password')
    },

    reinitHelpScoutInputType: function () {
      document.querySelector('#helpscout_api_key').setAttribute('type', this.helpscout.displayApiKey ? 'text' : 'password')
    },

    toogleEnvatoClientSecret: function () {
      this.envato.displayClientSecret = !this.envato.displayClientSecret;
      this.reinitClientSecretInputType();
    },

    toogleHelpscoutApiKey: function () {
      this.helpscout.displayApiKey = !this.helpscout.displayApiKey;
      this.reinitHelpScoutInputType();
    },

    refreshHelpscout() {
      window.location.href = 'admin.php?page=envascout-form-setting&refresh_mailbox=1'
    }
  },
  mounted: function () {
    this.reinitClientSecretInputType();
    this.reinitHelpScoutInputType();
    document.querySelector('.tab-content').removeAttribute('style');
  }
})