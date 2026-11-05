import { Component, type ErrorInfo, type ReactNode } from 'react'
import { Button, ErrorBanner } from './ui'

type Props = { children: ReactNode }
type State = { error: Error | null }

/**
 * Last-resort catch for render errors, so one broken screen doesn't blank the whole app.
 * The router remounts it per page (keyed by path), so navigating away also clears the error.
 */
export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null }

  static getDerivedStateFromError(error: Error): State {
    return { error }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error('Render error', error, info.componentStack)
  }

  render() {
    if (this.state.error) {
      return (
        <ErrorBanner
          title="Something went wrong on this page."
          action={
            <Button variant="secondary" size="sm" onClick={() => this.setState({ error: null })}>
              Try again
            </Button>
          }
        >
          {this.state.error.message}
        </ErrorBanner>
      )
    }

    return this.props.children
  }
}
