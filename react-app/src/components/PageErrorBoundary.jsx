import { Component } from 'react';

export default class PageErrorBoundary extends Component {
  state = { failed: false };
  static getDerivedStateFromError() { return { failed: true }; }
  componentDidCatch(error) { console.error('Page rendering failed:', error); }
  render() {
    if (!this.state.failed) return this.props.children;
    return <div className="page-content" role="alert">
      <h2>This page could not be displayed</h2>
      <p>Please reload the page. If it still fails, share the first red browser Console error with support.</p>
      <button type="button" className="btn btn-primary" onClick={() => window.location.reload()}>Reload page</button>
    </div>;
  }
}
