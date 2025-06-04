import React from 'react';
import { FormattedMessage } from 'react-intl';
import { createStructuredSelector } from 'reselect';
import { injectIntl } from 'react-intl';
import { compose } from 'redux';
import { connect } from 'react-redux';

import Typography from '@material-ui/core/Typography';
import Button from '@material-ui/core/Button';
import Card from '@material-ui/core/Card';
import CardContent from '@material-ui/core/CardContent';

import MainContentWrapper from 'components/MainContentWrapper';
import ConfigurationListItem from 'components/ConfigurationListItem';
import StyledButtonLink from 'components/StyledButtonLink';

import { makeSelectConfigurations } from 'containers/App/selectors';
import { getConfigurationsRequest } from 'containers/App/actions';

class PublishedConfigurationsPage extends React.Component {
    constructor(props) {
        super(props);

        this.state = {
            loading: false,
            userId: false,
        }
    }

    componentWillMount() {
        if (this.props.match && this.props.match.params && this.props.match.params.id) {
            this.setState({
                userId: this.props.match.params.id
            });

            this.props.dispatch(getConfigurationsRequest({userId: this.props.match.params.id}));
        }
    }

    render() {
        let configurations = [];
        if (this.props.configurations && this.props.configurations.length > 0) {
            this.props.configurations.map((item, index) => {
                let parsedData = JSON.parse(item.value);
                if (parsedData.published) {
                    configurations.push(<ConfigurationListItem
                        key={`configuration_card_${index}`}
                        ownerScreenName={this.state.userId}
                        expanded={true}
                        readOnly={true}
                        data={item}/>);
                }
            });
        }

        let appBaseURL = (process.env.WEBPACK_PUBLIC_PATH ? process.env.WEBPACK_PUBLIC_PATH : `/`);
        return (<div>
            <MainContentWrapper>
                <Card>
                    <CardContent>
                        <div>
                            <Typography variant="h6" color="inherit">
                                <FormattedMessage id="Configurations of"/> {this.state.userId}

                                <StyledButtonLink to={appBaseURL}>
                                    <Button
                                        variant="contained"
                                        size="small"
                                        color="primary"
                                        style={{marginLeft: `10px`}}>
                                        <FormattedMessage id="Dashboard" />
                                    </Button>
                                </StyledButtonLink>
                            </Typography>
                        </div>
                        <div>{configurations}</div>
                    </CardContent>
                </Card>
            </MainContentWrapper>
        </div>);
    }
}

const mapStateToProps = createStructuredSelector({
    configurations: makeSelectConfigurations()
});

const withConnect = connect(mapStateToProps);

export default compose(withConnect)(injectIntl(PublishedConfigurationsPage));
