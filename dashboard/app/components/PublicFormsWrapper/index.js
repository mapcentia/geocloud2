import React from 'react';
import styled from 'styled-components';
import { FormattedMessage } from 'react-intl';

import Card from '@material-ui/core/Card';
import CardContent from '@material-ui/core/CardContent';
import Typography from '@material-ui/core/Typography';

const LogoWrapper = styled.div`
    max-height: 200px;
    padding-bottom: 30px;
`;

const wrapper = (props) => {
    const { children } = props;
    return (<Card style={{ maxWidth: `400px` }}>
        <CardContent>
            <LogoWrapper>
                <img src={props.logo} style={{ maxWidth: `150px`, height: `auto` }}/>
            </LogoWrapper>
            <Typography variant="h5" gutterBottom>
                <FormattedMessage id={`welcomeDescription`} />
            </Typography>
            <div>
                {children}
            </div>
        </CardContent>
    </Card>);
} 

export default wrapper;
